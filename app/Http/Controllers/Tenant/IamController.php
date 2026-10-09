<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\GeneratesPasswords;
use App\Http\Controllers\Controller;
use App\Models\NotificationGroup;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use App\Services\LoginService;
use App\Services\Notify;
use App\Services\PlanLimiter;
use App\Support\Excel;
use App\Support\Passwords;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/** Tenant IAM: users within plan limits, generate password, set-password link, unlock, CSV import. */
class IamController extends Controller
{
    use GeneratesPasswords;

    public function index(Request $request)
    {
        $q = User::with('role', 'groups')->orderBy('name');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"));
        }
        if ($r = $request->query('role')) {
            $q->where('role_id', $r);
        }
        if ($request->query('status') === 'locked') {
            $q->where('locked_until', '>', now());
        } elseif ($request->query('status') !== null && $request->query('status') !== '') {
            $q->where('is_active', $request->query('status') === 'active');
        }
        $tenant = app(Tenancy::class)->get();

        return view('ws.iam.index', [
            'users' => $q->paginate(25)->withQueryString(),
            'roles' => Role::orderBy('name')->get(),
            'usage' => PlanLimiter::usage($tenant),
        ]);
    }

    public function create(Request $request)
    {
        return view('ws.iam.form', ['user' => new User(['is_active' => true]), 'roles' => $this->assignableRoles($request->user()), 'groups' => NotificationGroup::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $data = $this->validated($request);
        $role = Role::findOrFail($data['role_id']);
        abort_unless($this->assignableRoles($actor)->contains('id', $role->id), 403);
        $tenant = app(Tenancy::class)->get();
        PlanLimiter::ensureUserRole($tenant, $role->base_role);

        $user = DB::transaction(function () use ($data, $tenant, $role) {
            $u = User::create([
                'tenant_id' => $tenant->id,
                'role_id' => $role->id,
                'user_code' => IdGenerator::next($tenant->id, 'user'),
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'mobile' => $data['mobile'] ?? null,
                'password' => Passwords::generate(),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'must_change_password' => true,
            ]);
            $u->groups()->sync(NotificationGroup::whereIn('id', $data['groups'] ?? [])->pluck('id'));

            return $u;
        });

        if ($request->input('password_mode') === 'generate') {
            $plain = LoginService::generatePassword($user, $request->boolean('must_change', true), $request->boolean('email_user'), $actor);

            return redirect()->route('ws.iam.index')->with('ok', "User {$user->name} created.")->with('generated', [
                'name' => $user->name, 'email' => $user->email, 'password' => $plain, 'emailed' => $request->boolean('email_user'), 'must_change' => $request->boolean('must_change', true),
            ]);
        }
        $this->sendInvite($user, $actor);

        return redirect()->route('ws.iam.index')->with('ok', "User {$user->name} created. A set-password link was emailed to {$user->email}.");
    }

    public function edit(Request $request, User $user)
    {
        $this->guardTarget($request->user(), $user);

        return view('ws.iam.form', ['user' => $user, 'roles' => $this->assignableRoles($request->user()), 'groups' => NotificationGroup::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        $this->guardTarget($actor, $user);
        $data = $this->validated($request, $user);
        $role = Role::findOrFail($data['role_id']);
        abort_unless($this->assignableRoles($actor)->contains('id', $role->id), 403);
        if ($user->id === $actor->id && ($role->id !== $user->role_id || empty($data['is_active']))) {
            return back()->with('error', 'You cannot change your own role or disable yourself.');
        }
        if ($role->base_role !== $user->role->base_role) {
            PlanLimiter::ensureUserRole($user->tenant, $role->base_role);
        }
        $user->update([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'mobile' => $data['mobile'] ?? null,
            'role_id' => $role->id,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        $user->groups()->sync(NotificationGroup::whereIn('id', $data['groups'] ?? [])->pluck('id'));
        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return redirect()->route('ws.iam.index')->with('ok', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete yourself.');
        if ($user->isTenantAdmin() && User::whereHas('role', fn ($q) => $q->where('base_role', 'tenant_admin'))->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Keep at least one active Tenant Admin.');
        }
        $user->groups()->detach();
        $user->delete();

        return back()->with('ok', 'User deleted.');
    }

    public function generatePassword(Request $request, User $user)
    {
        return $this->generateFor($request, $user);
    }

    public function bulkPasswords(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        return $this->bulkGenerate($request, User::whereIn('id', $request->ids)->get(), 'passwords-'.now()->format('YmdHis').'.xlsx');
    }

    public function resetLink(Request $request, User $user)
    {
        $this->guardTarget($request->user(), $user);
        $this->sendInvite($user, $request->user(), true);

        return back()->with('ok', "A password-reset link was emailed to {$user->email}.");
    }

    public function unlock(Request $request, User $user)
    {
        abort_unless($request->user()->isTenantAdmin(), 403, 'Only a Tenant Admin can unlock accounts.');
        LoginService::unlock($user);

        return back()->with('ok', "{$user->name} is unlocked.");
    }

    /** Bulk import: full_name,email,mobile,role,notification_groups (from the template's Users sheet). */
    public function import(Request $request)
    {
        $request->validate(['csv' => 'required_without:file|nullable|string|max:200000', 'file' => 'nullable|file|max:2048|extensions:csv,txt']);
        $text = $request->hasFile('file') ? file_get_contents($request->file('file')->getRealPath()) : $request->input('csv');
        $parsed = Excel::parseCsvText((string) $text);
        $need = ['full_name', 'email', 'mobile', 'role'];
        if (array_diff($need, $parsed['headers'])) {
            return back()->with('error', 'The first line must be: full_name,email,mobile,role,notification_groups');
        }
        $tenant = app(Tenancy::class)->get();
        $roleMap = ['admin' => 'tenant_admin', 'manager' => 'tenant_manager', 'sales' => 'tenant_sales', 'accountant' => 'tenant_account', 'account' => 'tenant_account', 'support' => 'support'];
        $groups = NotificationGroup::pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [strtolower($n) => $id]);
        $errors = [];
        $valid = [];
        $seen = [];
        foreach ($parsed['rows'] as $r) {
            $line = $r['_row'];
            $email = strtolower((string) ($r['email'] ?? ''));
            $mobile = preg_replace('/\D/', '', (string) ($r['mobile'] ?? ''));
            $base = $roleMap[strtolower((string) ($r['role'] ?? ''))] ?? null;
            if (! ($r['full_name'] ?? null) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($mobile) !== 10 || ! $base) {
                $errors[] = "Line $line: name, a valid email, a 10-digit mobile and a role (Admin, Manager, Sales, Accountant, Support) are required.";

                continue;
            }
            if (isset($seen[$email]) || User::withoutGlobalScopes()->where('email', $email)->exists()) {
                $errors[] = "Line $line: $email already exists.";

                continue;
            }
            if ($request->user()->baseRole() === 'support' && $base === 'tenant_admin') {
                $errors[] = "Line $line: Support users cannot create Tenant Admins.";

                continue;
            }
            $seen[$email] = true;
            $gids = collect(preg_split('/[;,]/', (string) ($r['notification_groups'] ?? '')))->map(fn ($g) => $groups[strtolower(trim($g))] ?? null)->filter()->values()->all();
            $valid[] = compact('r', 'email', 'mobile', 'base', 'gids');
        }
        if ($errors) {
            return back()->with('error', 'Nothing imported. Fix these lines: '.implode(' ', array_slice($errors, 0, 10)));
        }
        $created = DB::transaction(function () use ($valid, $tenant, $request) {
            $n = 0;
            foreach ($valid as $v) {
                PlanLimiter::ensureUserRole($tenant, $v['base']);
                $u = User::create([
                    'tenant_id' => $tenant->id, 'role_id' => Role::systemRole($v['base'], $tenant->id)->id,
                    'user_code' => IdGenerator::next($tenant->id, 'user'), 'name' => $v['r']['full_name'], 'email' => $v['email'],
                    'mobile' => $v['mobile'], 'password' => Passwords::generate(), 'must_change_password' => true,
                ]);
                $u->groups()->sync($v['gids']);
                $this->sendInvite($u, $request->user());
                $n++;
            }

            return $n;
        });

        return back()->with('ok', "$created user(s) imported. Each received a set-password link by email.");
    }

    private function sendInvite(User $user, User $actor, bool $reset = false): void
    {
        $token = Password::broker('users')->createToken($user);
        $link = route('password.reset', ['token' => $token, 'email' => $user->email]);
        if ($reset) {
            Notify::send('password_reset', [$user->email], ['name' => $user->name, 'link' => $link], $user->tenant_id, now: true);
        } else {
            Notify::send('user_invited', [$user->email], [
                'name' => $user->name, 'tenant_name' => $user->tenant->name, 'inviter' => $actor->name, 'role' => $user->role->name, 'link' => $link,
            ], $user->tenant_id);
        }
        AuditLogger::log($reset ? 'reset_link_sent' : 'user_invited', $user, null, ['email' => $user->email], $user->tenant_id);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'mobile' => ['nullable', 'regex:/^\d{10}$/'],
            'role_id' => 'required|integer',
            'groups' => 'array',
            'groups.*' => 'integer|exists:notification_groups,id',
            'is_active' => 'nullable|boolean',
            'password_mode' => 'nullable|in:generate,link',
        ], ['mobile.regex' => 'Mobile must be 10 digits.']);
    }

    /** Support may manage tenant users except Tenant Admins. */
    private function guardTarget(User $actor, User $target): void
    {
        if ($actor->baseRole() === 'support' && $target->isTenantAdmin()) {
            abort(403, 'Support users cannot change a Tenant Admin.');
        }
    }

    private function assignableRoles(User $actor)
    {
        $roles = Role::orderBy('name')->get();

        return $actor->baseRole() === 'support' ? $roles->where('base_role', '!=', 'tenant_admin')->values() : $roles;
    }
}
