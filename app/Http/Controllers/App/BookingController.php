<?php

namespace App\Http\Controllers\App;

use App\Enums\PlotStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Plot;
use App\Services\BookingService;
use App\Services\OnlineBookingService;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function create(Plot $plot, Settings $settings): View
    {
        abort_unless($plot->status === PlotStatus::Available, 422, 'This plot is not available.');

        return view('app.bookings.create', [
            'plot' => $plot->load('layout'),
            'expires' => now()->addDays((int) $settings->get('booking_validity_days', 15)),
            'customers' => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name', 'phone']),
        ]);
    }

    public function store(Request $request, Plot $plot, BookingService $bookings): RedirectResponse
    {
        $data = $request->validate(CustomerController::rulesForInline() + [
            'amount' => ['required', 'numeric', 'min:1', 'max:'.(float) $plot->cost],
            'mode' => ['required', Rule::in(['cash', 'upi', 'neft', 'cheque', 'card'])],
            'reference_no' => ['nullable', 'required_unless:mode,cash', 'string', 'max:80'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $booking = DB::transaction(function () use ($data, $plot, $bookings) {
            $customer = CustomerController::resolveInline($data);

            return $bookings->book($plot, $customer, $data);
        });

        return $this->done("Plot {$plot->plot_no} booked until {$booking->expires_at->format('d M Y')}.", 'app.plots.show', $plot);
    }

    /** Confirm a website hold by recording the booking advance. */
    public function confirm(Request $request, Booking $booking, OnlineBookingService $online): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:'.(float) $booking->plot->cost],
            'mode' => ['required', Rule::in(['cash', 'upi', 'neft', 'cheque', 'card'])],
            'reference_no' => ['nullable', 'required_unless:mode,cash', 'string', 'max:80'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $online->confirm($booking, $data);

        return $this->done('Advance recorded. The website hold is now a confirmed booking.');
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings): RedirectResponse
    {
        $bookings->cancel($booking, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']);

        return $this->done('Booking cancelled; the plot is available again.');
    }
}
