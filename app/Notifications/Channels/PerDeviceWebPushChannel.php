<?php

namespace App\Notifications\Channels;

use App\Models\PushNotificationDelivery;
use App\Notifications\Concerns\InteractsWithWebPushService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use NotificationChannels\WebPush\PushSubscription;

class PerDeviceWebPushChannel
{
    use InteractsWithWebPushService;

    /**
     * Sends one push per device subscription, each with its own delivery
     * token. A single shared token would let one device's "received"
     * acknowledgement silently satisfy every other device's delivery,
     * stopping retries for devices the push never actually reached.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPushPayload') || ! method_exists($notifiable, 'pushSubscriptions')) {
            return;
        }

        $subscriptions = $notifiable->pushSubscriptions;

        if ($subscriptions->isEmpty()) {
            return;
        }

        /** @var array{title: string, icon?: string, body: string, data?: array<string, mixed>} $template */
        $template = $notification->toWebPushPayload($notifiable);
        $webPush = $this->makeWebPush();

        /** @var PushSubscription $subscription */
        foreach ($subscriptions as $subscription) {
            $token = Str::random(64);

            $payload = $template;
            $payload['tag'] = $token;
            $payload['data'] = array_merge($payload['data'] ?? [], ['delivery_token' => $token]);

            PushNotificationDelivery::create([
                'token_hash' => hash('sha256', $token),
                'push_subscription_id' => $subscription->id,
                'payload' => $payload,
                'last_sent_at' => now(),
            ]);

            $webPush->queueNotification(new Subscription(
                $subscription->endpoint,
                $subscription->public_key,
                $subscription->auth_token,
                $subscription->content_encoding ?? ContentEncoding::aes128gcm,
            ), json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $this->logWebPushReports($webPush->flush());
    }
}
