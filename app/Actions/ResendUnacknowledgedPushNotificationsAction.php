<?php

namespace App\Actions;

use App\Models\PushNotificationDelivery;
use App\Notifications\Concerns\InteractsWithWebPushService;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class ResendUnacknowledgedPushNotificationsAction
{
    use InteractsWithWebPushService;

    /**
     * Resend the stored payload for every device delivery that hasn't been
     * acknowledged as received within the retry window.
     */
    public function handle(?WebPush $webPush = null): int
    {
        $deliveries = PushNotificationDelivery::query()
            ->dueForRetry()
            ->with('pushSubscription')
            ->get();

        if ($deliveries->isEmpty()) {
            return 0;
        }

        $webPush ??= $this->makeWebPush();
        $resent = 0;

        foreach ($deliveries as $delivery) {
            $subscription = $delivery->pushSubscription;

            // The device's subscription is gone (e.g. the push service
            // already reported it expired) — nothing left to retry.
            if (! $subscription) {
                continue;
            }

            $webPush->queueNotification(new Subscription(
                $subscription->endpoint,
                $subscription->public_key,
                $subscription->auth_token,
                $subscription->content_encoding ?? ContentEncoding::aes128gcm,
            ), json_encode($delivery->payload, JSON_THROW_ON_ERROR));

            $delivery->markResent();
            $resent++;
        }

        $this->logWebPushReports($webPush->flush());

        return $resent;
    }
}
