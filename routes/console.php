<?php

use App\Console\Commands\CheckLeaveExcess;
use App\Console\Commands\GenerateMonthlyFeeInvoices;
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
