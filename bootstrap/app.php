<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Only trust proxy headers from the proxies you declare (e.g. Cloudflare ranges).
        $proxies = env('TRUSTED_PROXIES');
        if ($proxies) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }

        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            SetTenantContext::class,
        ]);

        // Payment-gateway webhooks authenticate with an HMAC signature instead of CSRF.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'subscribed' => EnsureSubscriptionActive::class,
            'super' => EnsureSuperAdmin::class,
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'noindex' => NoIndex::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeRoute() ?? '/');
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('vector7:expire-bookings')->dailyAt('00:30')->timezone('Asia/Kolkata')->withoutOverlapping();
        $schedule->command('vector7:send-reminders')->dailyAt('09:00')->timezone('Asia/Kolkata')->withoutOverlapping();
        $schedule->command('vector7:stage-alerts')->dailyAt('08:00')->timezone('Asia/Kolkata')->withoutOverlapping();
        $schedule->command('vector7:subscriptions')->dailyAt('01:00')->timezone('Asia/Kolkata')->withoutOverlapping();
        $schedule->command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
        $schedule->command('auth:clear-resets')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Never leak stack traces or SQL to users; details go to the log only.
        $exceptions->dontReportDuplicates();

        // Business-rule refusals (abort 422) on form posts go back to the form with a friendly message.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 422 && ! $request->expectsJson() && ! $request->isMethodSafe()) {
                return back()->withInput($request->except(['password', 'password_confirmation', 'aadhaar', 'pan']))->with('error', $e->getMessage());
            }
        });
    })->create();
