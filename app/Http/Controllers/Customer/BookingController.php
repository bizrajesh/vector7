<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Tenant;
use App\Services\SalesService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Marketplace booking by a signed-in customer (new customers register first, then book). */
class BookingController extends Controller
{
    public function create(Plot $plot)
    {
        $plot->load('project');
        abort_unless($plot->project?->status === 'launched', 404);
        $tenant = Tenant::findOrFail($plot->tenant_id);
        $settings = $tenant->setting();
        $first = \App\Models\InstalmentPlan::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('seq')->get();

        return view('account.book', [
            'plot' => $plot,
            'tenant' => $tenant,
            'settings' => $settings,
            'plan' => $first,
            'validTill' => \App\Services\WorkingDays::for($tenant->id)->add(today(), (int) $settings->booking_validity_days),
        ]);
    }

    public function store(Request $request, Plot $plot)
    {
        $data = $request->validate([
            'promo_code' => 'nullable|string|max:30',
            'disclaimer' => 'accepted',
        ], ['disclaimer.accepted' => __('Please read and accept the booking terms.')]);
        try {
            $booking = SalesService::book($plot, $request->user('customer'), 'marketplace', $data['promo_code'] ?? null, $request->ip());
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        } finally {
            app(Tenancy::class)->set(null);
        }

        return redirect()->route('account.bookings')->with('ok', __('Booked! Booking :no for Plot :plot is valid till :date. The promoter will contact you for the first instalment.', ['no' => $booking->booking_no, 'plot' => $plot->plot_no, 'date' => $booking->valid_till->format('d-m-Y')]));
    }
}
