<?php

namespace App\Support\Sms;

final readonly class SmsSendResult
{
    public function __construct(
        public bool $isSuccessful,
        public ?string $messageId = null,
        public ?string $error = null,
        public ?string $responseCode = null,
    ) {}

    public static function success(?string $messageId = null, ?string $responseCode = null): self
    {
        return new self(true, $messageId, responseCode: $responseCode);
    }

    public static function failure(string $error, ?string $responseCode = null): self
    {
        return new self(false, error: $error, responseCode: $responseCode);
    }
}
