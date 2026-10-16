<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AppSettings;
use App\Support\Format;
use App\Support\Tenancy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tenancy::class);
    }

    public function boot(): void
    {
        if (env('FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        Paginator::defaultView('partials.pagination');

        // "module.action" abilities resolve through the role permission matrix.
        Gate::before(function ($user, string $ability) {
            if ($user instanceof User && str_contains($ability, '.')) {
                return $user->hasPerm($ability) ? true : null;
            }

            return null;
        });

        Blade::directive('inr', fn ($e) => "<?php echo e(\App\Support\Format::inr($e)); ?>");
        Blade::directive('date', fn ($e) => "<?php echo e(\App\Support\Format::date($e)); ?>");

        $this->rateLimits();
        $this->mailFromSettings();
    }

    private function rateLimits(): void
    {
        $key = fn (Request $r) => $r->ip().'|'.strtolower((string) $r->input('email'));
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(10)->by($key($r)), Limit::perMinute(30)->by($r->ip())]);
        RateLimiter::for('forgot', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(20)->by($r->ip()));
        RateLimiter::for('genpass', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('enquiry', fn (Request $r) => Limit::perHour(20)->by($r->ip()));
        RateLimiter::for('ai', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        // Masked-number reveal / call / WhatsApp clicks (spec 17.5): per visitor and per number.
        RateLimiter::for('reveal', fn (Request $r) => [Limit::perMinute(5)->by('rv:'.$r->ip()), Limit::perDay(30)->by('rvd:'.$r->ip())]);
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(40)->by($r->ip()));
        RateLimiter::for('report', fn (Request $r) => Limit::perHour(10)->by($r->ip()));
        RateLimiter::for('chat', fn (Request $r) => [Limit::perMinute(12)->by('ch:'.($r->user()?->id ?: $r->ip())), Limit::perDay(200)->by('chd:'.($r->user()?->id ?: $r->ip()))]);
    }

    /** SMTP settings saved in App Settings override .env. */
    private function mailFromSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
            $host = AppSettings::get('smtp.host');
            if ($host && ! app()->runningUnitTests()) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $host,
                    'mail.mailers.smtp.port' => (int) AppSettings::get('smtp.port', 465),
                    'mail.mailers.smtp.scheme' => AppSettings::get('smtp.encryption') === 'ssl' ? 'smtps' : 'smtp',
                    'mail.mailers.smtp.username' => AppSettings::get('smtp.username'),
                    'mail.mailers.smtp.password' => AppSettings::get('smtp.password'),
                    'mail.from.address' => AppSettings::get('smtp.from_address', config('mail.from.address')),
                    'mail.from.name' => AppSettings::get('smtp.from_name', 'vector7'),
                ]);
            }
        } catch (\Throwable) {
            // database not ready (first install)
        }
    }
}
