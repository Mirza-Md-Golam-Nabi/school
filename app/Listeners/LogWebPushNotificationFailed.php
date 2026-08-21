<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\Events\NotificationFailed;

class LogWebPushNotificationFailed
{
    public function handle(NotificationFailed $event): void
    {
        Log::warning('WebPush notification failed.', [
            'endpoint' => $event->report->getEndpoint(),
            'reason' => $event->report->getReason(),
            'status_code' => $event->report->getResponse()?->getStatusCode(),
            'response_content' => $event->report->getResponseContent(),
            'subscription_expired' => $event->report->isSubscriptionExpired(),
        ]);
    }
}
