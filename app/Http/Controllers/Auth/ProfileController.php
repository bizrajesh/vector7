<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Passwords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Staff profile: name/mobile and change own password (current + new 8–32). */
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.profile', ['user' => $request->user('web')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'mobile' => ['nullable', 'regex:/^\d{10}$/']]);
        $request->user('web')->update($data);

        return back()->with('ok', 'Profile updated.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => Passwords::rules(),
        ], Passwords::messages());
        $user = $request->user('web');
        $user->forceFill(['password' => $request->password])->saveQuietly();
        Auth::guard('web')->logoutOtherDevices($request->password);
        AuditLogger::log('password_changed', $user, null, null, $user->tenant_id);

        return back()->with('ok', 'Password changed. Other sessions were signed out.');
    }
}
