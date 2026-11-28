<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an exam's marks / result reach a student: a push notification to the
 * student's own app, or an SMS to the guardian's number.
 */
enum ExamNotificationChannel: string implements HasLabel
{
    case Push = 'push';
    case Sms = 'sms';

    public function getLabel(): string
    {
        return match ($this) {
            self::Push => 'Push Notification',
            self::Sms => 'SMS (অভিভাবকের নম্বরে)',
        };
    }

    /**
     * Channels from a stored / submitted list, unknown values dropped.
     *
     * @param  iterable<mixed>|null  $values
     * @return list<self>
     */
    public static function listFrom(?iterable $values): array
    {
        return collect($values ?? [])
            ->map(fn (mixed $value): ?self => $value instanceof self ? $value : self::tryFrom((string) $value))
            ->filter()
            ->unique(fn (self $channel): string => $channel->value)
            ->values()
            ->all();
    }
}
