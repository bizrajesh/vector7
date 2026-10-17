<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Records every sign-in attempt for the App team's security screen. Never stores passwords. */
class LoginEvents
{
    public static function record(Request $request, string $portal, User|Customer|null $account, string $outcome, ?string $reason = null, ?string $email = null): void
    {
        try {
            LoginEvent::create([
                'portal' => $portal,
                'account_type' => $account instanceof Customer ? 'customer' : ($account ? 'user' : null),
                'account_id' => $account?->id,
                'tenant_id' => $account instanceof User ? $account->tenant_id : null,
                'email' => Str::limit(strtolower((string) ($email ?? $account?->email ?? $request->input('email'))), 250, ''),
                'outcome' => $outcome,
                'reason' => $reason ? Str::limit($reason, 78, '') : null,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
                'session_id' => $outcome === 'success' && $request->hasSession() ? $request->session()->getId() : null,
            ]);
        } catch (\Throwable $e) {
            report($e); // monitoring must never block a sign-in
        }
    }

    /** Short browser / device label from a user-agent string. */
    public static function device(?string $ua): string
    {
        $ua = (string) $ua;
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox', str_contains($ua, 'Safari/') => 'Safari', $ua === '' => 'Unknown', default => 'Other',
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android', str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS', str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS', str_contains($ua, 'Linux') => 'Linux', default => '',
        };

        return trim($browser.($os ? ' · '.$os : ''));
    }
}
