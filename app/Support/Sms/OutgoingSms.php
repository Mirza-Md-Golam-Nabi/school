<?php

namespace App\Support\Sms;

use App\Enums\SmsEvent;

/**
 * One SMS waiting to be queued: who gets it, what it says, and what it is about.
 */
final readonly class OutgoingSms
{
    public function __construct(
        public ?string $phone,
        public string $message,
        public SmsEvent $event,
        public ?int $sourceId = null,
        public ?string $recipientName = null,
    ) {}
}
