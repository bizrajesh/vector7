<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\FileStore;
use App\Services\SalesService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Offline payments (cash, cheque, bank transfer, UPI) recorded by Sales / Accounts, each with a numbered receipt. */
class PaymentController extends Controller
{
    public function store(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'mode' => ['required', Rule::in(array_keys(Payment::MODES))],
            'reference_no' => 'nullable|string|max:60',
            'paid_on' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'proof' => FileStore::rules('document', 10240, false),
        ]);
        try {
            $p = SalesService::recordPayment($sale, $data, $request->user()->id, $request->file('proof'));
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }
        $msg = "Payment recorded. Receipt {$p->transaction_no} was emailed to {$sale->customer->email}.";
        if ($sale->fresh()->status === 'ror') {
            $msg .= ' Fully paid — the plot is now ROR (ready for registration).';
        }

        return back()->with('ok', $msg);
    }

    public function receipt(Payment $payment)
    {
        return response(SalesService::receiptPdf($payment), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Receipt-'.$payment->transaction_no.'.pdf"']);
    }
}
