<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\GeneratesPasswords;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\AuditLogger;
use App\Services\LoginService;
use App\Services\Notify;
use App\Support\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/** App Customers: list, search, view; enable/disable, generate password, reset link, update on request. No delete. */
class CustomerController extends Controller
{
    use GeneratesPasswords;

    public function index(Request $request)
    {
        $q = Customer::withCount(['bookings' => fn ($b) => $b->withoutGlobalScopes(), 'sales' => fn ($s) => $s->withoutGlobalScopes()])->latest('id')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->when($request->query('status') === 'disabled', fn ($q) => $q->where('is_active', false))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true));
        if ($request->query('export') === 'xlsx') {
            return Excel::download('customers.xlsx', ['Name', 'Email', 'Mobile', 'City', 'Active', 'Bookings', 'Purchases', 'Joined'],
                $q->get()->map(fn ($c) => [$c->name, $c->email, $c->mobile, $c->city, $c->is_active, $c->bookings_count, $c->sales_count, $c->created_at]), 'Customers');
        }

        return view('app.customers.index', ['customers' => $q->paginate(30)->withQueryString()]);
    }

    public function show(Customer $customer)
    {
        return view('app.customers.show', [
            'c' => $customer->load('tenants'),
            'bookings' => Booking::withoutGlobalScopes()->where('customer_id', $customer->id)->with(['plot' => fn ($q) => $q->withoutGlobalScopes(), 'project' => fn ($q) => $q->withoutGlobalScopes()])->latest('id')->get(),
            'sales' => Sale::withoutGlobalScopes()->where('customer_id', $customer->id)->with(['plot' => fn ($q) => $q->withoutGlobalScopes(), 'project' => fn ($q) => $q->withoutGlobalScopes()])->latest('id')->get(),
            'payments' => Payment::withoutGlobalScopes()->where('customer_id', $customer->id)->latest('paid_on')->get(),
            'changes' => AuditLog::where('auditable_type', 'Customer')->where('auditable_id', $customer->id)->latest('id')->take(30)->get(),
        ]);
    }

    /** Update customer info on request — every change is audit-logged (who changed what). */
    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', Rule::unique('customers', 'email')->ignore($customer->id)],
            'mobile' => ['nullable', 'regex:/^\d{10}$/'],
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pin' => ['nullable', 'regex:/^\d{6}$/'],
            'reason' => 'required|string|max:255',
        ]);
        $reason = $data['reason'];
        unset($data['reason']);
        $customer->update($data + ['updated_by' => $request->user()->id]);
        AuditLogger::log('customer_updated_on_request', $customer, null, ['reason' => $reason]);

        return back()->with('ok', 'Customer updated.');
    }

    public function toggle(Customer $customer)
    {
        $customer->update(['is_active' => ! $customer->is_active]);

        return back()->with('ok', $customer->is_active ? 'Customer enabled.' : 'Customer disabled; they can no longer sign in.');
    }

    public function generatePassword(Request $request, Customer $customer)
    {
        return $this->generateFor($request, $customer);
    }

    public function bulkPasswords(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        return $this->bulkGenerate($request, Customer::whereIn('id', $request->ids)->get(), 'customer-passwords-'.now()->format('YmdHis').'.xlsx');
    }

    public function resetLink(Customer $customer)
    {
        $token = Password::broker('customers')->createToken($customer);
        Notify::send('password_reset', [$customer->email], ['name' => $customer->name, 'link' => route('customer.password.reset', ['token' => $token, 'email' => $customer->email])], null, now: true);
        AuditLogger::log('reset_link_sent', $customer, null, ['email' => $customer->email]);

        return back()->with('ok', "A password-reset link was emailed to {$customer->email}.");
    }

    public function unlock(Customer $customer)
    {
        LoginService::unlock($customer);

        return back()->with('ok', 'Customer unlocked.');
    }
}
