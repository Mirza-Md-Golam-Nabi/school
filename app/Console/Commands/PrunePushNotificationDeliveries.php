<?php

namespace App\Console\Commands;

use App\Models\PushNotificationDelivery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webpush:prune-deliveries')]
#[Description('Delete push notification delivery records older than a week.')]
class PrunePushNotificationDeliveries extends Command
{
    public function handle(): int
    {
        $deleted = PushNotificationDelivery::where('created_at', '<', now()->subWeek())->delete();

        $this->info("Deleted {$deleted} old push notification delivery record(s).");

        return self::SUCCESS;
    }
}
