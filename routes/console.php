<?php

use App\Console\Commands\CheckLeaveExcess;
use App\Console\Commands\GenerateMonthlyFeeInvoices;
use App\Console\Commands\PrunePushNotificationDeliveries;
use App\Console\Commands\ResendUnacknowledgedPushNotifications;
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

// Resend web push notifications not yet acknowledged as received by the device.
// Cadence follows push_notifications.retry_interval_minutes so the schedule and
// the "due for retry" window (see PushNotificationDelivery::scopeDueForRetry)
// never drift out of sync. withoutOverlapping() matters here specifically: a
// bulk event (e.g. publishing a result to hundreds of students at once) can
// take longer to resend than a short interval allows, and this command sends
// each push synchronously — without this guard, the next tick would start
// stacking runs on top of one still in progress.
Schedule::command(ResendUnacknowledgedPushNotifications::class)
    ->cron('*/'.config('push_notifications.retry_interval_minutes').' * * * *')
    ->withoutOverlapping();

// Delete push notification delivery records older than a week, daily —
// the command's own cutoff (created_at < 1 week ago, see
// PrunePushNotificationDeliveries) still decides what gets deleted; running
// daily instead of weekly just sweeps stale rows out sooner after they cross
// that age, rather than letting up to a week's worth pile up between runs.
Schedule::command(PrunePushNotificationDeliveries::class)->dailyAt('02:00');
