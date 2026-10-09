<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict Content-Security-Policy (no inline scripts; nonce for JSON-LD/data blocks),
 * HSTS on HTTPS, X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy.
 * Workspace and account pages are sent with X-Robots-Tag: noindex.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce'",
            "style-src 'self'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
        $h = $response->headers;
        if (! $h->has('Content-Security-Policy')) {
            $h->set('Content-Security-Policy', $csp);
        }
        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($request->is('workspace*', 'app', 'app/*', 'account*', 'files/*', 'profile*', 'login', 'register', 'forgot-password', 'reset-password*') || ! config('seo.indexable')) {
            $h->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
