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
