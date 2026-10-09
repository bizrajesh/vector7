<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Broker;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plot;
use App\Models\Project;
use App\Models\Sale;
use App\Services\FileStore;
use App\Services\SalesService;
use App\Support\Excel;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Sales: initiate (direct or from a booking), track instalments, payments, refunds. */
class SaleController extends Controller
{
    public function index(Request $request)
    {
        $q = Sale::with(['plot', 'project', 'customer', 'instalments'])->latest('id')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('location'), fn ($q, $l) => $q->whereHas('project', fn ($w) => $w->where('location', $l)))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('sale_no', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"))));
        if ($request->query('export') === 'xlsx' && $request->user()->hasPerm('sales.export')) {
            return Excel::download('sales.xlsx', ['Sale', 'Started', 'Project', 'Plot', 'Customer', 'Net price', 'Paid', 'Due', 'Window ends', 'Status'],
                $q->get()->map(fn ($s) => [$s->sale_no, $s->started_on, $s->project->name, $s->plot->plot_no, $s->customer->name, (float) $s->net_price, (float) $s->paid_amount, (float) $s->due_amount, $s->window_end, Sale::STATUSES[$s->status] ?? $s->status]), 'Sales');
        }

        return view('ws.sales.index', [
            'sales' => $q->paginate(25)->withQueryString(),
            'projects' => Project::launched()->orderBy('name')->pluck('name', 'id'),
            'locations' => Project::launched()->distinct()->pluck('location'),
            'totals' => ['value' => (float) Sale::whereIn('status', ['sale_init', 'ror', 'completed'])->sum('net_price'), 'paid' => (float) Sale::whereIn('status', ['sale_init', 'ror', 'completed'])->sum('paid_amount'), 'due' => (float) Sale::where('status', 'sale_init')->sum('due_amount')],
        ]);
    }

    public function create(Request $request)
    {
        $booking = $request->query('booking') ? Booking::with('plot.project', 'customer')->where('status', 'active')->find($request->query('booking')) : null;

        return view('ws.sales.sale-form', [
            'booking' => $booking,
            'projects' => Project::launched()->orderBy('name')->pluck('name', 'id'),
            'brokers' => Broker::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'settings' => app(Tenancy::class)->get()->setting(),
            'plan' => \App\Models\InstalmentPlan::orderBy('seq')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'booking_id' => 'nullable|integer',
            'plot_id' => 'required_without:booking_id|nullable|integer',
            'customer_id' => 'required_without:booking_id|nullable|integer',
            'amount' => 'required|numeric|min:1',
            'mode' => ['required', Rule::in(array_keys(Payment::MODES))],
            'reference_no' => 'nullable|string|max:60',
            'paid_on' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'broker_id' => 'nullable|integer',
            'promo_code' => 'nullable|string|max:30',
            'proof' => FileStore::rules('document', 10240, false),
            'disclaimer' => 'accepted',
        ], ['disclaimer.accepted' => 'The customer must accept the sale disclaimer.']);
        if (! empty($data['booking_id'])) {
            $booking = Booking::where('status', 'active')->findOrFail($data['booking_id']);
            $plot = $booking->plot;
            $customer = $booking->customer;
            if (! empty($data['customer_id']) && (int) $data['customer_id'] !== $customer->id) {
                return back()->withInput()->with('error', 'A booked plot can only be sold to the customer who booked it.');
            }
        } else {
            $plot = Plot::findOrFail($data['plot_id']);
            $customer = app(Tenancy::class)->get()->customers()->where('customers.id', $data['customer_id'])->first();
            if (! $customer) {
                return back()->withInput()->with('error', 'Choose a customer of this workspace (or create a booking for a new customer first).');
            }
        }
        try {
            $sale = SalesService::initiateSale($plot, $customer, $data, $data['broker_id'] ?? null, $data['promo_code'] ?? null, $request->ip(), $request->user()->id, $request->file('proof'));
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('ws.sales.show', $sale)->with('ok', "Sale {$sale->sale_no} started. Plot {$plot->plot_no} is now Sale Init and a receipt was emailed.");
    }

    public function show(Sale $sale)
    {
        $sale->load(['plot', 'project', 'customer', 'instalments', 'payments.receipt', 'payments.proof', 'booking', 'broker', 'refund', 'registration']);

        return view('ws.sales.sale', [
            'sale' => $sale,
            'quote' => $sale->status === 'sale_init' && $sale->isWindowMissed() ? SalesService::refundQuote($sale) : null,
        ]);
    }
}
