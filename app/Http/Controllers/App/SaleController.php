<?php

namespace App\Http\Controllers\App;

use App\Enums\PlotStatus;
use App\Http\Controllers\Controller;
use App\Models\Broker;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plot;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::in(['ongoing', 'paid', 'registered', 'cancelled', 'overdue'])]]);

        return view('app.sales.index', [
            'sales' => Sale::query()->with(['plot.layout', 'customer'])
                ->when($request->status === 'overdue', fn ($q) => $q->where('status', 'ongoing')->where('due_by', '<', now()->toDateString()))
                ->when($request->status && $request->status !== 'overdue', fn ($q) => $q->where('status', $request->status))
                ->latest()->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Plot $plot): View
    {
        abort_unless(in_array($plot->status, [PlotStatus::Available, PlotStatus::Booked], true), 422, 'Only Available or Booked plots can be sold.');

        return view('app.sales.create', [
            'plot' => $plot->load(['layout', 'activeBooking.customer']),
            'brokers' => Broker::query()->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name', 'phone']),
        ]);
    }

    public function store(Request $request, Plot $plot): RedirectResponse
    {
        $tenantId = app(TenantContext::class)->id();

        // Selling a booked plot with no customer entered means "sell to the booking customer".
        if ($plot->status === PlotStatus::Booked && ! $request->filled('customer_id') && ! $request->filled('customer.name')) {
            $request->merge(['customer_id' => $plot->activeBooking?->customer_id, 'customer' => null]);
        }

        $data = $request->validate(CustomerController::rulesForInline() + [
            'broker_id' => ['nullable', Rule::exists('brokers', 'id')->where('tenant_id', $tenantId)],
            'override_reason' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:'.(float) $plot->cost],
            'mode' => ['required_with:amount', 'nullable', Rule::in(['cash', 'upi', 'neft', 'cheque', 'card'])],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $sale = DB::transaction(function () use ($data, $plot) {
            $customer = CustomerController::resolveInline($data);

            $payment = ! empty($data['amount']) ? [
                'amount' => $data['amount'], 'mode' => $data['mode'], 'reference_no' => $data['reference_no'] ?? null,
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
            ] : null;

            return $this->sales->create($plot, $customer, $data, $payment);
        });

        return $this->done('Sale created. Instalments are scheduled within the sale window.', 'app.sales.show', $sale);
    }

    public function show(Sale $sale): View
    {
        return view('app.sales.show', ['sale' => $sale->load(['plot.layout', 'customer', 'broker', 'instalments', 'payments', 'registration'])]);
    }

    public function storePayment(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'mode' => ['required', Rule::in(['cash', 'upi', 'neft', 'cheque', 'card'])],
            'reference_no' => ['nullable', 'required_unless:mode,cash', 'string', 'max:80'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = $this->sales->recordPayment($sale, $data);

        return $this->done("Payment recorded. Receipt {$payment->receipt_no}.");
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        $this->sales->cancel($sale, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']);

        return $this->done('Sale cancelled. Record any refund in Accounting.');
    }

    public function receipt(Payment $payment): View
    {
        return view('print.receipt', ['payment' => $payment->load(['customer', 'sale.plot.layout', 'booking.plot.layout'])]);
    }
}
