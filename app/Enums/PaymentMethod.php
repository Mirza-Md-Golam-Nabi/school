<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Cheque = 'cheque';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Cheque => 'Cheque',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::BankTransfer => 'info',
            self::Bkash => 'pink',
            self::Nagad => 'orange',
            self::Cheque => 'gray',
        };
    }
}
