<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Testing-only gate: until the visitor enters the shared preview email + password, every page redirects to
 * /preview-login. Payment webhooks and the health check stay open. Off unless PREVIEW_GATE=true.
 */
class PreviewGate
{
    public const COOKIE = 'v7preview';

    public const OPEN_PATHS = ['preview-login', 'webhooks/*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('vector7.preview_gate.enabled') || $request->is(...self::OPEN_PATHS) || self::passed($request)) {
            $response = $next($request);
            if (config('vector7.preview_gate.enabled')) {
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            }

            return $response;
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Preview sign-in required.'], 401);
        }
        if ($request->isMethod('GET')) {
            $request->session()->put('preview.intended', $request->fullUrl());
        }

        return redirect()->route('preview.login');
    }

    public static function token(): string
    {
        $c = config('vector7.preview_gate');

        return hash_hmac('sha256', $c['email'].'|'.$c['password_hash'], (string) config('app.key'));
    }

    public static function passed(Request $request): bool
    {
        $v = (string) $request->cookie(self::COOKIE, '');

        return $v !== '' && config('vector7.preview_gate.password_hash') !== '' && hash_equals(self::token(), $v);
    }
}
