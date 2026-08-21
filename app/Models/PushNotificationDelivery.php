<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NotificationChannels\WebPush\PushSubscription;

#[Fillable(['token_hash', 'push_subscription_id', 'payload', 'attempts', 'last_sent_at', 'received_at'])]
class PushNotificationDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function pushSubscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class);
    }

    public function markReceived(): void
    {
        $this->forceFill(['received_at' => $this->received_at ?? now()])->save();
    }

    public function markResent(): void
    {
        $this->forceFill([
            'attempts' => $this->attempts + 1,
            'last_sent_at' => now(),
        ])->save();
    }

    /**
     * Deliveries not yet acknowledged as received, whose last send attempt
     * was more than `push_notifications.retry_interval_minutes` ago and
     * haven't yet hit `push_notifications.max_attempts` — due for a resend.
     */
    public function scopeDueForRetry(Builder $query): void
    {
        $query->whereNull('received_at')
            ->where('attempts', '<', config('push_notifications.max_attempts'))
            ->where('last_sent_at', '<=', now()->subMinutes(config('push_notifications.retry_interval_minutes')));
    }
}
