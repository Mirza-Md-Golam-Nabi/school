<?php

namespace App\Support\Sms;

/**
 * What happened when a batch of SMS was offered for sending. A batch is all
 * or nothing: when the balance can't cover every message, none is queued.
 */
final readonly class SmsBatchResult
{
    public const QUEUED = 'queued';

    public const NOTHING_TO_SEND = 'nothing_to_send';

    public const DISABLED = 'disabled';

    public const NO_VENDOR = 'no_vendor';

    public const INSUFFICIENT_BALANCE = 'insufficient_balance';

    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';

    public function __construct(
        public string $status,
        public int $queued = 0,
        public int $skippedInvalidPhone = 0,
        public int $parts = 0,
        public float $cost = 0,
        public float $balance = 0,
    ) {}

    public function isQueued(): bool
    {
        return $this->status === self::QUEUED;
    }

    public function isRejected(): bool
    {
        return in_array($this->status, [self::NO_VENDOR, self::INSUFFICIENT_BALANCE, self::PROVIDER_UNAVAILABLE], true);
    }

    /**
     * A message for the admin explaining why nothing was sent, in Bengali.
     */
    public function rejectionMessage(): string
    {
        return match ($this->status) {
            self::INSUFFICIENT_BALANCE => sprintf(
                'SMS ব্যালেন্স অপর্যাপ্ত: %d টি SMS (%d পার্ট)-এর জন্য ৳%s লাগবে, কিন্তু ব্যালেন্স আছে ৳%s। কোনো SMS পাঠানো হয়নি — রিচার্জ করুন।',
                $this->queued,
                $this->parts,
                number_format($this->cost, 2),
                number_format($this->balance, 2),
            ),
            self::NO_VENDOR => 'কোনো SMS vendor চালু নেই, তাই SMS পাঠানো হয়নি।',
            self::PROVIDER_UNAVAILABLE => 'SMS সেবা এই মুহূর্তে উপলব্ধ নেই, তাই SMS পাঠানো হয়নি। সাপোর্টের সাথে যোগাযোগ করুন।',
            default => '',
        };
    }
}
