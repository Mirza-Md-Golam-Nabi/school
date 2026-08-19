<?php

use App\Console\Commands\CheckLeaveExcess;
use App\Console\Commands\GenerateMonthlyFeeInvoices;
use App\Jobs\DeactivateExpiredActingAdminsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generate monthly fee invoices on the 1st of every month at 7:00 AM
Schedule::command(GenerateMonthlyFeeInvoices::class)->monthlyOn(1, '07:00');

// Check leave excess daily at 8:00 AM — creates excess logs for absent employees
Schedule::command(CheckLeaveExcess::class)->dailyAt('08:00');

// Deactivate acting-admin records whose to_date has passed, daily at 12:10 AM
Schedule::job(new DeactivateExpiredActingAdminsJob)->dailyAt('00:10');

// Delete activity log entries older than activitylog.delete_records_older_than_days (730 days), weekly
Schedule::command('activitylog:clean')->weeklyOn(0, '01:00');
