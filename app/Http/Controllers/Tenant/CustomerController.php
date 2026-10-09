<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Sale;
use App\Support\Excel;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Tenant customers — read-only, and only customers associated with this tenant through a booking or purchase.
 * A guessed customer ID that is not associated returns 404.
 */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $tenant = app(Tenancy::class)->get();
        $q = $tenant->customers()
            ->withCount(['bookings' => fn ($b) => $b->where('tenant_id', $tenant->id), 'sales' => fn ($s) => $s->where('tenant_id', $tenant->id)])
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->orderBy('customers.name');
        if ($request->query('export') === 'xlsx' && $request->user()->hasPerm('tenant_customers.export')) {
            return Excel::download('customers.xlsx', ['Name', 'Email', 'Mobile', 'City', 'Bookings', 'Purchases', 'Associated since'],
                $q->get()->map(fn ($c) => [$c->name, $c->email, $c->mobile, $c->city, $c->bookings_count, $c->sales_count, $c->pivot->created_at]), 'Customers');
        }

        return view('ws.customers.index', ['customers' => $q->paginate(25)->withQueryString()]);
    }

    public function show(int $customer)
    {
        $c = app(Tenancy::class)->get()->customers()->where('customers.id', $customer)->firstOrFail();

        return view('ws.customers.show', [
            'c' => $c,
            'bookings' => Booking::where('customer_id', $c->id)->with(['plot:id,plot_no', 'project:id,name'])->latest('id')->get(),
            'sales' => Sale::where('customer_id', $c->id)->with(['plot:id,plot_no', 'project:id,name'])->latest('id')->get(),
            'payments' => Payment::where('customer_id', $c->id)->with(['plot:id,plot_no', 'receipt'])->latest('paid_on')->get(),
            'registrations' => Registration::where('customer_id', $c->id)->with(['plot:id,plot_no', 'project:id,name'])->latest('id')->get(),
        ]);
    }
}
