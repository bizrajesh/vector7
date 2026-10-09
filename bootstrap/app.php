<?php

use App\Http\Middleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware as MiddlewareConfig;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (MiddlewareConfig $middleware): void {
        $middleware->web(append: [Middleware\SecurityHeaders::class]);
        $middleware->alias([
            'staff' => Middleware\EnsureStaff::class,
            'appuser' => Middleware\EnsureAppUser::class,
            'tenant' => Middleware\EnsureTenantUser::class,
            'perm' => Middleware\CheckPermission::class,
            'module' => Middleware\CheckPlanModule::class,
            'customer' => Middleware\EnsureCustomer::class,
        ]);
        // Tenant must be set before route-model binding so guessed IDs of other tenants 404.
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, Middleware\EnsureTenantUser::class);
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn (Request $r) => $r->is('workspace*', 'app', 'app/*', 'files/*', 'profile*') ? route('staff.login') : route('login'));
        $middleware->redirectUsersTo(function (Request $r) {
            if ($r->user('web') && $r->is('workspace*')) {
                return $r->user('web')->homeRoute();
            }

            return route('account.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->expectsJson());
    })->create();
