<?php

namespace App\Console\Commands;

use App\Actions\ResendUnacknowledgedPushNotificationsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webpush:resend-unacknowledged')]
#[Description('Resend web push notifications the device has not acknowledged as received.')]
class ResendUnacknowledgedPushNotifications extends Command
{
    public function handle(ResendUnacknowledgedPushNotificationsAction $action): int
    {
        $resent = $action->handle();

        $this->info("Resent {$resent} unacknowledged push notification(s).");

        return self::SUCCESS;
    }
}
