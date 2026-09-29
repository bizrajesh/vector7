<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PurchaseRequest;
use App\Models\Sale;
use App\Services\OnlineBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Read-only customer view, scoped to the user's own customer record. */
class CustomerPortalController extends Controller
{
    public function home(Request $request): View
    {
        $customer = $request->user()->customer;
        abort_unless($customer, 403, 'Your login is not linked to a customer record.');

        return view('portal.customer', [
            'customer' => $customer,
            'sales' => Sale::query()->where('customer_id', $customer->id)->where('status', '!=', 'cancelled')
                ->with(['plot.layout', 'instalments', 'payments', 'registration'])->latest()->get(),
            'bookings' => $customer->bookings()->whereIn('status', ['pending', 'active'])->with('plot.layout')->latest()->get(),
            'openRequests' => PurchaseRequest::query()->where('customer_id', $customer->id)->where('status', 'new')->pluck('booking_id')->all(),
            'salesContact' => $request->user()->tenant,
        ]);
    }

    /** "I want to buy" — the sales team completes every purchase. Only the customer's own bookings qualify. */
    public function requestPurchase(Request $request, OnlineBookingService $service): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer, 403);
        $data = $request->validate(['booking_id' => ['required', 'integer'], 'message' => ['nullable', 'string', 'max:500']]);

        $booking = Booking::query()->where('customer_id', $customer->id)->whereIn('status', ['pending', 'active'])->findOrFail($data['booking_id']);
        abort_if(PurchaseRequest::query()->where('booking_id', $booking->id)->where('status', 'new')->exists(), 422, 'Your request is already with our sales team.');

        $service->requestPurchase([
            'layout_id' => $booking->plot->layout_id, 'plot_id' => $booking->plot_id, 'customer_id' => $customer->id,
            'booking_id' => $booking->id, 'name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email,
            'message' => $data['message'] ?? null, 'type' => 'purchase', 'source' => 'portal',
        ]);

        return back()->with('status', 'Thank you. Our sales team will contact you to complete the purchase.');
    }

    public function receipt(Request $request, Payment $payment): View
    {
        abort_unless($request->user()->customer_id && $payment->customer_id === $request->user()->customer_id, 404);

        return view('print.receipt', ['payment' => $payment->load(['customer', 'sale.plot.layout', 'booking.plot.layout'])]);
    }
}
