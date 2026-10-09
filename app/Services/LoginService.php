<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Support\Passwords;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Email + password login with account lockout (5 wrong passwords → 15 minutes),
 * session regeneration, and password generation for IAM.
 */
class LoginService
{
    public static function attempt(Request $request, string $guard): User|Customer
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:64'],
        ]);
        $model = $guard === 'customer' ? Customer::class : User::class;
        $account = app(Tenancy::class)->withoutScope(fn () => $model::query()->withoutGlobalScopes()->where('email', strtolower(trim($data['email'])))->first());
        $fail = fn (string $msg) => throw ValidationException::withMessages(['email' => $msg]);

        if (! $account) {
            Hash::check($data['password'], '$2y$12$'.str_repeat('a', 53)); // equalise timing
            $fail('These credentials do not match our records.');
        }
        if ($account->isLocked()) {
            $mins = max(1, (int) ceil(now()->diffInMinutes($account->locked_until)));
            $fail("Too many wrong passwords. This account is locked for $mins more minute(s). An administrator can unlock it.");
        }
        if (! Hash::check($data['password'], $account->password)) {
            $attempts = $account->failed_logins + 1;
            $lock = $attempts >= config('auth.lockout.attempts', 5);
            $account->forceFill([
                'failed_logins' => $lock ? 0 : $attempts,
                'locked_until' => $lock ? now()->addMinutes(config('auth.lockout.minutes', 15)) : null,
            ])->saveQuietly();
            if ($lock) {
                AuditLogger::log('account_locked', $account, null, ['email' => $account->email], $account->tenant_id ?? null);
                $fail('Too many wrong passwords. This account is locked for 15 minutes.');
            }
            $fail('These credentials do not match our records.');
        }
        if (! $account->is_active) {
            $fail('This account is disabled. Contact your administrator.');
        }
        if ($account instanceof User && $account->tenant_id && $account->tenant()->first()?->status !== 'active') {
            $fail('This workspace is suspended. Contact vector7 support.');
        }

        $account->forceFill(['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => now()])->saveQuietly();
        Auth::guard($guard)->login($account, $request->boolean('remember'));
        $request->session()->regenerate();
        AuditLogger::log('login', $account, null, null, $account->tenant_id ?? null);

        return $account;
    }

    /**
     * Generate a 12-character password, store only the hash, log it (never the password),
     * log the user out of other sessions and optionally force a change at next login.
     */
    public static function generatePassword(User|Customer $target, bool $mustChange = true, bool $email = false, ?User $actor = null): string
    {
        $plain = Passwords::generate();
        $target->forceFill([
            'password' => $plain,
            'must_change_password' => $mustChange,
            'remember_token' => \Illuminate\Support\Str::random(60),
            'failed_logins' => 0,
            'locked_until' => null,
        ])->saveQuietly();
        if ($target instanceof User) {
            DB::table('sessions')->where('user_id', $target->id)->delete();
        }
        AuditLogger::log('password_generated', $target, null, [
            'for' => $target->email,
            'by' => $actor?->email,
            'must_change' => $mustChange,
            'emailed' => $email,
        ], $target instanceof User ? $target->tenant_id : null);

        if ($email) {
            Notify::send('password_generated', [$target->email], [
                'name' => $target->name,
                'admin' => $actor?->name ?? 'vector7',
                'email' => $target->email,
                'password' => $plain,
                'change_note' => $mustChange ? 'You will be asked to set a new password after you sign in.' : '',
                'link' => $target instanceof User ? route('staff.login') : route('login'),
            ], $target instanceof User ? $target->tenant_id : null, now: true);
        }

        return $plain;
    }

    public static function unlock(User|Customer $target): void
    {
        $target->forceFill(['failed_logins' => 0, 'locked_until' => null])->saveQuietly();
        AuditLogger::log('account_unlocked', $target, null, ['email' => $target->email], $target instanceof User ? $target->tenant_id : null);
    }
}
