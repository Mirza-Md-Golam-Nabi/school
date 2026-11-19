<?php

namespace App\Support\Sms;

use App\Models\SmsVendor;

/**
 * One SMS provider's HTTP API.
 */
interface SmsGateway
{
    public function __construct(SmsVendor $vendor);

    /**
     * @param  string  $phone  Normalized 8801XXXXXXXXX number.
     */
    public function send(string $phone, string $message): SmsSendResult;

    /**
     * The provider account's real balance, or null when it can't be read.
     * Used only for internal checks — it must never be shown to anyone.
     */
    public function balance(): ?float;
}
