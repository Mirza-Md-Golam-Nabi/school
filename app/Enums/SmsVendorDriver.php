<?php

namespace App\Enums;

use App\Support\Sms\BulkSmsBdGateway;
use App\Support\Sms\SmsGateway;
use Filament\Support\Contracts\HasLabel;

/**
 * The SMS providers this app can talk to. Supporting another provider means a
 * new case here plus a class implementing SmsGateway.
 */
enum SmsVendorDriver: string implements HasLabel
{
    case BulkSmsBd = 'bulksmsbd';

    public function getLabel(): string
    {
        return match ($this) {
            self::BulkSmsBd => 'BulkSMSBD',
        };
    }

    /**
     * @return class-string<SmsGateway>
     */
    public function gatewayClass(): string
    {
        return match ($this) {
            self::BulkSmsBd => BulkSmsBdGateway::class,
        };
    }
}
