<?php

namespace App\Actions;

use App\Models\PushNotificationDelivery;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\PushSubscription;

class ResendUnacknowledgedPushNotificationsAction
{
    /**
     * Resend the stored payload for every delivery the service worker hasn't
     * acknowledged as received within the retry window. `WebPush::class` is
     * only container-bound for `WebPushChannel` (see WebPushServiceProvider),
     * so a fresh, identically-configured instance is built here rather than
     * injected — unless a preconfigured one is passed in (tests substitute a
     * fake PSR-18 client this way instead of hitting a real push service).
     */
    public function handle(?WebPush $webPush = null): int
    {
        $deliveries = PushNotificationDelivery::query()
            ->dueForRetry()
            ->with('notifiable')
            ->get();

        if ($deliveries->isEmpty()) {
            return 0;
        }

        $webPush ??= $this->makeWebPush();
        $resent = 0;

        foreach ($deliveries as $delivery) {
            $notifiable = $delivery->notifiable;

            if (! $notifiable || ! method_exists($notifiable, 'pushSubscriptions')) {
                continue;
            }

            $subscriptions = $notifiable->pushSubscriptions;

            if ($subscriptions->isEmpty()) {
                continue;
            }

            $payload = json_encode($delivery->payload, JSON_THROW_ON_ERROR);

            /** @var PushSubscription $subscription */
            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(new Subscription(
                    $subscription->endpoint,
                    $subscription->public_key,
                    $subscription->auth_token,
                    $subscription->content_encoding ?? ContentEncoding::aes128gcm,
                ), $payload);
            }

            $delivery->markResent();
            $resent++;
        }

        $this->handleReports($webPush->flush());

        return $resent;
    }

    /**
     * @param  \Generator<int, MessageSentReport>  $reports
     */
    private function handleReports(\Generator $reports): void
    {
        foreach ($reports as $report) {
            if ($report->isSuccess()) {
                Log::info('WebPush notification resent and accepted by the push service.', [
                    'endpoint' => $report->getEndpoint(),
                ]);

                continue;
            }

            if ($report->isSubscriptionExpired()) {
                PushSubscription::findByEndpoint($report->getEndpoint())?->delete();
            }

            Log::warning('WebPush notification resend failed.', [
                'endpoint' => $report->getEndpoint(),
                'reason' => $report->getReason(),
                'status_code' => $report->getResponse()?->getStatusCode(),
            ]);
        }
    }

    private function makeWebPush(): WebPush
    {
        return (new WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid.subject') ?: url('/'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ]))
            ->setReuseVAPIDHeaders(true)
            ->setAutomaticPadding((bool) config('webpush.automatic_padding', true));
    }
}
