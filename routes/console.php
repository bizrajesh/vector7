<?php

use App\Jobs\MatchRequirements;
use App\Services\FileStore;
use App\Services\Scheduler;
use App\Services\SeoService;
use App\Services\SocialService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| One cron entry drives everything (Hostinger: hPanel → Advanced → Cron Jobs):
|   * * * * * php /home/USER/vector7/artisan schedule:run >> /dev/null 2>&1
| schedule:run also processes the database queue (emails) with queue:work --stop-when-empty.
*/

Artisan::command('v7:bookings', fn () => $this->info(json_encode(Scheduler::bookings())))->purpose('Release expired bookings and send expiry reminders');
Artisan::command('v7:offers', fn () => $this->info(Scheduler::offers().' expired offers hidden'))->purpose('Hide offers that have expired');
Artisan::command('v7:instalments', fn () => $this->info(json_encode(Scheduler::instalments())))->purpose('Instalment due reminders and overdue notices');
Artisan::command('v7:subscriptions', function () {
    SubscriptionService::dailyRun();
    $this->info('Subscriptions checked');
})->purpose('Subscription expiry, renewal reminders, usage alerts, monthly usage snapshot');
Artisan::command('v7:storage', function () {
    FileStore::recalculateUsage();
    $this->info('Storage usage recalculated');
})->purpose('Verify each tenant\'s storage usage');
Artisan::command('v7:social', fn () => $this->info(SocialService::publishDue().' post(s) published'))->purpose('Publish scheduled social posts');
Artisan::command('v7:social-metrics', function () {
    SocialService::pullMetrics();
    $this->info('Social metrics pulled');
})->purpose('Pull reach, likes, comments, followers');
Artisan::command('v7:requirements', function () {
    MatchRequirements::dispatchSync();
    $this->info('Requirement matches emailed');
})->purpose('Email customers about new plots matching saved requirements');
Artisan::command('v7:backup', fn () => $this->info('Backup written: '.Scheduler::backup()))->purpose('Daily database + files backup to the selected storage driver');
/*
| Reset (or create) an App Admin from the command line — for when nobody can sign in.
|   php artisan v7:app-admin                      → uses APP_ADMIN_EMAIL / APP_ADMIN_PASSWORD from .env
|   php artisan v7:app-admin you@x.com --password="New@Pass123"
*/
Artisan::command('v7:app-admin {email? : Defaults to APP_ADMIN_EMAIL} {--password= : Defaults to APP_ADMIN_PASSWORD}', function () {
    $email = strtolower((string) ($this->argument('email') ?: env('APP_ADMIN_EMAIL', 'admin@vector7.in')));
    $password = (string) ($this->option('password') ?: env('APP_ADMIN_PASSWORD', 'ChangeMe@2026'));
    $v = \Illuminate\Support\Facades\Validator::make(['email' => $email, 'password' => $password], ['email' => 'required|email', 'password' => \App\Support\Passwords::rules(false)]);
    if ($v->fails()) {
        $this->error(implode(' ', $v->errors()->all()));

        return 1;
    }
    $role = \App\Models\Role::whereNull('tenant_id')->where('base_role', 'app_admin')->first();
    if (! $role) {
        $this->error('App roles are missing. Run: php artisan migrate --seed');

        return 1;
    }
    $user = \App\Models\User::withoutGlobalScopes()->where('email', $email)->first();
    if ($user && $user->tenant_id !== null) {
        $this->error("$email belongs to a tenant workspace, not the App workspace. Use another email.");

        return 1;
    }
    $user ??= new \App\Models\User(['email' => $email, 'name' => env('APP_ADMIN_NAME', 'Vector7 Admin'), 'tenant_id' => null]);
    $user->forceFill(['role_id' => $role->id, 'password' => $password, 'is_active' => true, 'must_change_password' => false, 'failed_logins' => 0, 'locked_until' => null])->save();
    \App\Services\AuditLogger::log('app_admin_reset_cli', $user, null, ['email' => $email]);
    $this->info("App Admin ready: $email — sign in at ".rtrim((string) config('app.url'), '/').'/workspace/login');

    return 0;
})->purpose('Create or reset the App Admin (Super Admin) login');

Artisan::command('seo:build {--ai : Write titles/descriptions with Claude}', fn () => $this->info(json_encode(SeoService::buildAll((bool) $this->option('ai')))))->purpose('Generate/refresh meta titles, descriptions, JSON-LD data and the sitemap');

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('v7:bookings')->hourly()->withoutOverlapping();
Schedule::command('v7:offers')->dailyAt('00:05');
Schedule::command('v7:instalments')->dailyAt('08:00');
Schedule::command('v7:subscriptions')->dailyAt('06:00');
Schedule::command('v7:storage')->dailyAt('01:30');
Schedule::command('v7:social')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('v7:social-metrics')->dailyAt('05:00');
Schedule::command('v7:requirements')->dailyAt('09:00');
Schedule::command('seo:build')->dailyAt('03:00');
Schedule::command('v7:backup')->dailyAt('02:00')->withoutOverlapping();
