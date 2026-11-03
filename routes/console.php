<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$scheduleTimezone = appTimezone();

// Drop failed-job records older than 7 days so the table stays small.
Schedule::command('queue:prune-failed --hours=168')
    ->daily();

Schedule::command('app:clean-old-logs')
    ->daily()
    ->withoutOverlapping();

Schedule::job(new \App\Jobs\GenerateGenericFeedJob)
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('app:update-enquiry-open-game-status')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('app:send-daily-enquiry-open-game-reminder')
    ->dailyAt('09:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping();

Schedule::command('app:send-due-task-reminders')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('app:send-enquiry-inactivity-warning')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('app:send-listing-expiry-reminder')
    ->dailyAt('08:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping();

Schedule::command('app:send-listing-aged-reminder')
    ->dailyAt('08:30')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping();

Schedule::command('app:send-overdue-task-notifications')
    ->dailyAt('09:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping();

Schedule::command('app:send-target-performance-emails')
    ->dailyAt('07:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping();

// Run automated birthday and holiday greetings check every minute (filters internally by send_time)
Schedule::command('greetings:send-automated')
    ->everyMinute()
    ->withoutOverlapping();

// Daily HR Document Expiry Check (30, 15, 7, 0 days)
Schedule::command('hr:check-document-expiries')
    ->dailyAt('08:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping();

/*
Schedule::command('app:send-hot-enquiry-no-activity')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('app:send-whatsapp-followups')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('app:push-enquiry-linked-listings')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('app:send-daily-enquiry-matching')
    ->dailyAt('16:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping();

Schedule::command('app:send-daily-listing-matching')
    ->dailyAt('16:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping();
Schedule::command('app:send-overdue-followups-summary')
    ->dailyAt('07:30')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping();
*/

// Lead imports poll every five minutes over a rolling 2-hour window, so each run
// re-reads leads it has already seen; importLead skips those on contact + listing.
// The explicit 10-minute overlap lock matters at this cadence: the default expiry is
// 24 hours, so a run killed mid-flight would otherwise silently block every later run
// for a day.
Schedule::command('app:import-property-finder-leads')
    ->everyFiveMinutes()
    ->timezone($scheduleTimezone)
    ->withoutOverlapping(10);

Schedule::command('app:import-bayut-leads')
    ->everyFiveMinutes()
    ->timezone($scheduleTimezone)
    ->withoutOverlapping(10);
