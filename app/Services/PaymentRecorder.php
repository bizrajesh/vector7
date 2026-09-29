<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plot;
use App\Models\Sale;
use Illuminate\Support\Str;

/**
 * Creates a customer payment with a receipt number and posts it to the ledger.
 */
class PaymentRecorder
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function record(Customer $customer, array $data, Plot $plot, ?Booking $booking = null, ?Sale $sale = null): Payment
    {
        $payment = new Payment([
            'amount' => round((float) $data['amount'], 2),
            'mode' => $data['mode'],
            'reference_no' => $data['reference_no'] ?? null,
            'paid_at' => $data['paid_at'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
        ]);
        $payment->customer_id = $customer->id;
        $payment->booking_id = $booking?->id;
        $payment->sale_id = $sale?->id;
        $payment->receipt_no = 'TMP-'.Str::uuid();
        $payment->created_by = auth()->id();
        $payment->save();

        $payment->receipt_no = 'RCPT-'.now()->format('Y').'-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
        $payment->save();

        $this->ledger->post([
            'layout_id' => $plot->layout_id,
            'direction' => 'in',
            'type' => 'sales_income',
            'category' => 'Plot sales',
            'amount' => $payment->amount,
            'entry_date' => $payment->paid_at->toDateString(),
            'party' => $customer->name,
            'mode' => $payment->mode,
            'reference_no' => $payment->receipt_no,
            'description' => ($booking ? 'Booking' : 'Sale').' payment for plot '.$plot->plot_no,
        ], $payment);

        return $payment;
    }
}
