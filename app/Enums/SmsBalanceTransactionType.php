<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SmsBalanceTransactionType: string implements HasColor, HasLabel
{
    case Recharge = 'recharge';
    case Debit = 'debit';
    case Refund = 'refund';

    public function getLabel(): string
    {
        return match ($this) {
            self::Recharge => 'Recharge',
            self::Debit => 'SMS Cost',
            self::Refund => 'Refund',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Recharge => 'success',
            self::Debit => 'danger',
            self::Refund => 'info',
        };
    }
}
