<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\GeneratesPasswords;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginService;
use App\Services\Notify;
use App\Support\Passwords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/** App IAM (App Admin only): App users, plus password tools for every tenant user. */
class IamController extends Controller
{
    use GeneratesPasswords;

    public function index(Request $request)
    {
        return view('app.iam.index', [
            'users' => User::whereNull('tenant_id')->with('role')->orderBy('name')->get(),
            'roles' => Role::whereNull('tenant_id')->orderBy('name')->get(),
        ]);
    }

    public function tenantUsers(Request $request)
    {
        $q = User::whereNotNull('tenant_id')->with(['role' => fn ($r) => $r->withoutGlobalScopes(), 'tenant'])->orderBy('name');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }
        if ($t = $request->query('tenant')) {
            $q->where('tenant_id', $t);
        }

        return view('app.iam.tenant-users', ['users' => $q->paginate(30)->withQueryString(), 'tenants' => Tenant::orderBy('name')->pluck('name', 'id')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'mobile' => ['nullable', 'regex:/^\d{10}$/'],
            'role_id' => ['required', Rule::exists('roles', 'id')->whereNull('tenant_id')],
        ]);
        $plain = Passwords::generate();
        $user = User::create($data + ['tenant_id' => null, 'password' => $plain, 'must_change_password' => true, 'email' => strtolower($data['email'])]);
        if ($request->input('password_mode') === 'generate') {
            $plain = LoginService::generatePassword($user, $request->boolean('must_change', true), $request->boolean('email_user'), $request->user());

            return back()->with('ok', 'App user created.')->with('generated', ['name' => $user->name, 'email' => $user->email, 'password' => $plain, 'emailed' => $request->boolean('email_user'), 'must_change' => $request->boolean('must_change', true)]);
        }
        $this->link($user, false);

        return back()->with('ok', 'App user created; a set-password link was emailed.');
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->isAppUser(), 404);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile' => ['nullable', 'regex:/^\d{10}$/'],
            'role_id' => ['required', Rule::exists('roles', 'id')->whereNull('tenant_id')],
        ]);
        $active = $request->boolean('is_active');
        if ($user->id === $request->user()->id && (! $active || (int) $data['role_id'] !== $user->role_id)) {
            return back()->with('error', 'You cannot change your own role or disable yourself.');
        }
        $user->update($data + ['is_active' => $active]);
        if (! $active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('ok', 'User updated.');
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

    public function resetLink(User $user)
    {
        $this->link($user, true);

        return back()->with('ok', "A password-reset link was emailed to {$user->email}.");
    }

    public function unlock(User $user)
    {
        LoginService::unlock($user);

        return back()->with('ok', "{$user->name} is unlocked.");
    }

    private function link(User $user, bool $reset): void
    {
        $token = Password::broker('users')->createToken($user);
        $link = route('password.reset', ['token' => $token, 'email' => $user->email]);
        Notify::send($reset ? 'password_reset' : 'user_invited', [$user->email], ['name' => $user->name, 'link' => $link, 'tenant_name' => $user->tenant?->name ?? 'vector7', 'inviter' => 'vector7', 'role' => $user->role?->name], $user->tenant_id, now: true);
        AuditLogger::log($reset ? 'reset_link_sent' : 'user_invited', $user, null, ['email' => $user->email], $user->tenant_id);
    }
}
