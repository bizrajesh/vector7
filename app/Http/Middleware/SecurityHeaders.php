<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (OWASP A05 security misconfiguration).
 * Scripts are self-hosted only, so the CSP needs no third-party script hosts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            // Inline style attributes are used for widths of progress bars; no inline scripts are allowed.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        if ($request->isSecure() || app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (! app()->isProduction()) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }
}
