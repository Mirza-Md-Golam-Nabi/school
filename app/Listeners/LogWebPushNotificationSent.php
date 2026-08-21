<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\Events\NotificationSent;

class LogWebPushNotificationSent
{
    public function handle(NotificationSent $event): void
    {
        Log::info('WebPush notification accepted by the push service.', [
            'endpoint' => $event->report->getEndpoint(),
            'status_code' => $event->report->getResponse()?->getStatusCode(),
        ]);
    }
}
