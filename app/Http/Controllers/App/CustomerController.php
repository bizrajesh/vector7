<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = $request->q ? addcslashes($request->q, '%_') : null;

        return view('app.customers.index', [
            'customers' => Customer::query()->withCount('sales')
                ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
                ->orderBy('name')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Customer $customer): View
    {
        return view('app.customers.show', ['customer' => $customer->load(['bookings.plot', 'sales.plot', 'sales.payments'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($request->validate(self::rules()));

        return $this->done('Customer saved.', 'app.customers.show', $customer);
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'aadhaar' => ['nullable', 'regex:/^[0-9]{4}\s?[0-9]{4}\s?[0-9]{4}$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
        ];
    }

    /** Rules for "pick an existing customer or enter a new one" forms (booking, sale). */
    public static function rulesForInline(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'customer.name' => ['required_without:customer_id', 'nullable', 'string', 'max:120'],
            'customer.phone' => ['required_without:customer_id', 'nullable', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'customer.email' => ['nullable', 'email', 'max:190'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'customer.aadhaar' => ['nullable', 'regex:/^[0-9]{4}\s?[0-9]{4}\s?[0-9]{4}$/'],
            'customer.pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
        ];
    }

    public static function resolveInline(array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()->findOrFail($data['customer_id']);
        }

        return Customer::create(array_filter($data['customer'] ?? [], fn ($v) => $v !== null && $v !== ''));
    }
}
