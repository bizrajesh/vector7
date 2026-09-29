<?php

namespace App\Http\Controllers\Site;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Track my booking": passwordless sign-in for customers with an email one-time code.
 * Responses are identical whether or not the email exists (no account enumeration).
 */
class TrackController extends Controller
{
    public function show(): View
    {
        return view('public.track');
    }

    public function send(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = strtolower($data['email']);

        if (User::query()->where('email', $email)->where('role', Role::Customer)->where('status', 'active')->exists()) {
            $otp->send($email, 'track');
        }
        $request->session()->put('track_email', $email);

        return redirect()->route('track.verify')->with('status', 'If a booking exists for this email, we have sent a 6-digit code.');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        return $request->session()->has('track_email')
            ? view('public.track-verify', ['email' => $request->session()->get('track_email')])
            : redirect()->route('track');
    }

    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        $email = $request->session()->get('track_email');
        abort_unless($email, 404);
        $request->validate(['code' => ['required', 'digits:6']]);

        $otp->verify($email, 'track', $request->input('code'));

        $user = User::query()->where('email', $email)->where('role', Role::Customer)->where('status', 'active')->firstOrFail();
        $request->session()->forget('track_email');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.customer');
    }
}
