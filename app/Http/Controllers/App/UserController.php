<?php

namespace App\Http\Controllers\App;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Shareholder;
use App\Models\User;
use App\Services\PlanLimits;
use App\Support\TenantContext;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * IAM (section 3): the tenant Admin creates users for every tenant role.
 * New users receive a password-set link; Admin never sees or sets their password.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('app.users.index', ['users' => User::query()->inCurrentTenant()->with(['shareholder', 'customer'])->orderBy('role')->orderBy('name')->paginate(25)]);
    }

    public function create(): View
    {
        return view('app.users.form', ['user' => new User, 'options' => $this->options()]);
    }

    public function store(Request $request, PlanLimits $limits, TenantContext $context): RedirectResponse
    {
        $limits->ensureCanAddUser();
        $data = $this->validated($request);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'password' => Str::password(32)]);
        $user->tenant_id = $context->id();
        $this->applyRole($user, $data);
        $user->status = 'active';
        $user->save();

        event(new Registered($user));
        Password::sendResetLink(['email' => $user->email]);

        return $this->done("User created. {$user->email} will receive an email to set their password.", 'app.users.index');
    }

    public function edit(User $user): View
    {
        $this->ensureSameTenant($user);

        return view('app.users.form', ['user' => $user, 'options' => $this->options()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureSameTenant($user);
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ($data['role'] !== 'admin' || $data['status'] !== 'active')) {
            return back()->withErrors(['role' => 'You cannot remove your own Admin access.']);
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
        $this->applyRole($user, $data);
        $user->status = $data['status'];
        $user->save();

        return $this->done('User updated.', 'app.users.index');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'role' => ['required', Rule::in(config('vector7.tenant_roles'))],
            'status' => [$user ? 'required' : 'nullable', Rule::in(['active', 'disabled'])],
            'shareholder_id' => ['nullable', 'required_if:role,shareholder', Rule::exists('shareholders', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'customer_id' => ['nullable', 'required_if:role,customer', Rule::exists('customers', 'id')->where('tenant_id', app(TenantContext::class)->id())],
        ]);
    }

    private function applyRole(User $user, array $data): void
    {
        $user->role = Role::from($data['role']);
        $user->shareholder_id = $data['role'] === 'shareholder' ? $data['shareholder_id'] : null;
        $user->customer_id = $data['role'] === 'customer' ? $data['customer_id'] : null;
    }

    private function ensureSameTenant(User $user): void
    {
        abort_unless($user->tenant_id === app(TenantContext::class)->id(), 404);
    }

    private function options(): array
    {
        return [
            'shareholders' => Shareholder::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
        ];
    }
}
