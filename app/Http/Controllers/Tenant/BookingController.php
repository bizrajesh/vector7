<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Plot;
use App\Models\Project;
use App\Services\Notify;
use App\Services\SalesService;
use App\Support\Excel;
use App\Support\Passwords;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/** Bookings from the tenant workspace (Sales): existing customer or register a new one (set-password link emailed). */
class BookingController extends Controller
{
    public function index(Request $request)
    {
        $q = Booking::with(['plot', 'project', 'customer'])->latest('id')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('location'), fn ($q, $l) => $q->whereHas('project', fn ($w) => $w->where('location', $l)))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('booking_no', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"))));
        if ($request->query('export') === 'xlsx' && $request->user()->hasPerm('bookings.export')) {
            return Excel::download('bookings.xlsx', ['Booking', 'Date', 'Project', 'Plot', 'Customer', 'Mobile', 'Net price', 'Valid till', 'Status', 'Source'],
                $q->get()->map(fn ($b) => [$b->booking_no, $b->booked_on, $b->project->name, $b->plot->plot_no, $b->customer->name, $b->customer->mobile, (float) $b->net_price, $b->valid_till, Booking::STATUSES[$b->status], $b->source]), 'Bookings');
        }

        return view('ws.sales.bookings', [
            'bookings' => $q->paginate(25)->withQueryString(),
            'projects' => Project::launched()->orderBy('name')->pluck('name', 'id'),
            'locations' => Project::launched()->distinct()->pluck('location'),
        ]);
    }

    public function create(Request $request)
    {
        return view('ws.sales.booking-form', [
            'projects' => Project::launched()->orderBy('name')->pluck('name', 'id'),
            'disclaimer' => app(Tenancy::class)->get()->setting()->disclaimer_booking,
            'plot' => $request->query('plot') ? Plot::with('project')->find($request->query('plot')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plot_id' => 'required|integer',
            'customer_id' => 'nullable|integer',
            'new_name' => 'required_without:customer_id|nullable|string|max:100',
            'new_email' => 'required_without:customer_id|nullable|email|max:255',
            'new_mobile' => ['required_without:customer_id', 'nullable', 'regex:/^[6-9]\d{9}$/'],
            'promo_code' => 'nullable|string|max:30',
            'disclaimer' => 'accepted',
        ], ['disclaimer.accepted' => 'The customer must accept the booking disclaimer.', 'new_mobile.regex' => 'Enter a 10-digit mobile number.']);
        $plot = Plot::findOrFail($data['plot_id']);
        $customer = $this->resolveCustomer($data);
        try {
            $booking = SalesService::book($plot, $customer, 'workspace', $data['promo_code'] ?? null, $request->ip(), $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('ws.bookings.show', $booking)->with('ok', "Booking {$booking->booking_no} created for {$customer->name}. Valid till {$booking->valid_till->format('d-m-Y')}.");
    }

    public function show(Booking $booking)
    {
        return view('ws.sales.booking', ['booking' => $booking->load(['plot', 'project', 'customer', 'sale', 'promoCode'])]);
    }

    public function cancel(Booking $booking)
    {
        if ($booking->status !== 'active') {
            return back()->with('error', 'Only an active booking can be cancelled.');
        }
        SalesService::releaseBooking($booking, 'cancelled', 'Booking cancelled');

        return back()->with('ok', 'Booking cancelled; the plot is available again.');
    }

    /** JSON for the plot picker. */
    public function plots(Request $request)
    {
        $statuses = array_intersect(explode(',', (string) $request->query('statuses', 'available')), ['available', 'booked']);

        return Plot::where('project_id', (int) $request->query('project'))->whereIn('status', $statuses ?: ['available'])
            ->orderByRaw('CAST(plot_no AS UNSIGNED), plot_no')->get()
            ->map(fn ($p) => ['id' => $p->id, 'no' => $p->plot_no, 'sqft' => (float) $p->size_sqft, 'price' => $p->currentPrice(), 'actual' => $p->actualPrice(), 'offer' => $p->offerIsActive() ? $p->offerPrice() : null, 'status' => $p->status]);
    }

    /** JSON typeahead: only customers associated with this tenant (others are invisible). */
    public function customers(Request $request)
    {
        $s = trim((string) $request->query('q'));

        return app(Tenancy::class)->get()->customers()->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"))
            ->limit(10)->get()->map(fn ($c) => ['id' => $c->id, 'label' => "$c->name · $c->mobile · $c->email"]);
    }

    private function resolveCustomer(array $data): Customer
    {
        $tenant = app(Tenancy::class)->get();
        if (! empty($data['customer_id'])) {
            return $tenant->customers()->where('customers.id', $data['customer_id'])->firstOrFail();
        }
        $email = strtolower($data['new_email']);
        $customer = Customer::where('email', $email)->first();
        if (! $customer) {
            $customer = Customer::create(['name' => $data['new_name'], 'email' => $email, 'mobile' => $data['new_mobile'], 'password' => Passwords::generate()]);
            $token = Password::broker('customers')->createToken($customer);
            Notify::send('customer_welcome', [$customer->email], ['name' => $customer->name, 'tenant_name' => $tenant->name, 'link' => route('customer.password.reset', ['token' => $token, 'email' => $customer->email])], $tenant->id);
        }

        return $customer;
    }
}
