<?php

namespace App\Notifications\Concerns;

use Generator;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Shared by anything that sends push notifications directly through
 * Minishlink\WebPush rather than the laravel-notification-channels/webpush
 * package's own channel — `WebPush::class` is only container-bound for that
 * package's WebPushChannel (see WebPushServiceProvider), so a fresh,
 * identically-configured instance is built here instead.
 */
trait InteractsWithWebPushService
{
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

    /**
     * @param  Generator<int, MessageSentReport>  $reports
     */
    private function logWebPushReports(Generator $reports): void
    {
        foreach ($reports as $report) {
            if ($report->isSuccess()) {
                Log::info('WebPush notification accepted by the push service.', [
                    'endpoint' => $report->getEndpoint(),
                ]);

                continue;
            }

            if ($report->isSubscriptionExpired()) {
                PushSubscription::findByEndpoint($report->getEndpoint())?->delete();
            }

            Log::warning('WebPush notification failed.', [
                'endpoint' => $report->getEndpoint(),
                'reason' => $report->getReason(),
                'status_code' => $report->getResponse()?->getStatusCode(),
            ]);
        }
    }
}
