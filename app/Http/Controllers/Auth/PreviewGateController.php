<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\PreviewGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;

/** The testing sign-in page in front of the whole site (see config/vector7.php). */
class PreviewGateController extends Controller
{
    public function show(Request $request)
    {
        if (! config('vector7.preview_gate.enabled') || PreviewGate::passed($request)) {
            return redirect()->route('home');
        }

        return response()->view('auth.preview-login')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:100']);
        $c = config('vector7.preview_gate');
        $ok = $c['email'] !== '' && $c['password_hash'] !== ''
            && hash_equals($c['email'], strtolower(trim($data['email'])))
            && Hash::check($data['password'], $c['password_hash']);
        \App\Services\LoginEvents::record($request, 'preview', null, $ok ? 'success' : 'failed', $ok ? 'Preview gate' : 'Preview gate: wrong email or password', $data['email']);
        if (! $ok) {
            return back()->withInput(['email' => $data['email']])->withErrors(['email' => 'These details are not correct.']);
        }
        $request->session()->regenerate();
        $to = $request->session()->pull('preview.intended', route('home'));
        Cookie::queue(PreviewGate::COOKIE, PreviewGate::token(), 60 * 24 * max(1, $c['remember_days']), null, null, null, true, false, 'Lax');

        return redirect()->to(str_starts_with($to, url('/')) ? $to : route('home'));
    }
}
