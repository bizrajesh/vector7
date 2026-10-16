<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language for the public marketplace (English / Tamil), chosen with ?lang=ta|en and remembered in the
 * session and a 1-year cookie. Every other part of vector7 — promoter workspace, App workspace, files,
 * staff profile, webhooks — is always English.
 */
class SetMarketLocale
{
    public const LOCALES = ['en' => 'English', 'ta' => 'தமிழ்'];

    public const ENGLISH_ONLY = ['workspace', 'workspace/*', 'app', 'app/*', 'files/*', 'profile', 'profile/*', 'webhooks/*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::ENGLISH_ONLY)) {
            App::setLocale('en');

            return $next($request);
        }
        $chosen = $request->query('lang');
        if (is_string($chosen) && isset(self::LOCALES[$chosen])) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $chosen);
            }
            Cookie::queue('v7lang', $chosen, 60 * 24 * 365, null, null, null, true, false, 'Lax');
        }
        $locale = $chosen && isset(self::LOCALES[$chosen]) ? $chosen
            : ($request->hasSession() ? $request->session()->get('locale') : null) ?? $request->cookie('v7lang');
        App::setLocale(isset(self::LOCALES[$locale ?? '']) ? $locale : 'en');

        return $next($request);
    }

    /** The current URL in another language (for the switcher and hreflang links). */
    public static function url(string $locale): string
    {
        return request()->fullUrlWithQuery(['lang' => $locale]);
    }
}
