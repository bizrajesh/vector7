<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\RazorpayGateway;
use App\Services\Settings;
use App\Support\SecurityLog;
use App\Support\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context and one settings cache per request / job.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(Settings::class);
        $this->app->bind(PaymentGateway::class, RazorpayGateway::class);
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Surface mass-assignment mistakes during development instead of silently dropping fields.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Password policy (OWASP A07). "uncompromised" checks the HIBP k-anonymity API in production.
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers()->symbols();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Brute-force protection (OWASP A07).
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('password', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by(strtolower((string) ($request->input('email') ?? $request->session()->get('online_booking.email') ?? $request->session()->get('track_email'))).'|'.$request->ip()),
            Limit::perHour(10)->by($request->ip()),
        ]);
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinutes(10, 10)->by($request->ip()));
        RateLimiter::for('public-forms', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        // Every permission in the matrix becomes a Gate (usable as @can in Blade).
        foreach (collect(config('vector7.permissions'))->flatten()->unique() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // Security event logging (OWASP A09).
        Event::listen(Failed::class, fn (Failed $e) => SecurityLog::warning('login_failed', ['email_hash' => hash('sha256', strtolower((string) ($e->credentials['email'] ?? '')))]));
        Event::listen(Lockout::class, fn () => SecurityLog::warning('login_lockout'));
        Event::listen(Login::class, function (Login $e) {
            SecurityLog::info('login_success', ['user_id' => $e->user->getAuthIdentifier()]);
            $e->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
        });

        Blade::directive('inr', fn ($expression) => "<?php echo e(\\App\\Support\\Money::inr({$expression})); ?>");
        Blade::directive('inrShort', fn ($expression) => "<?php echo e(\\App\\Support\\Money::short({$expression})); ?>");
    }
}
