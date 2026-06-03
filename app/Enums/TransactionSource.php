<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TransactionSource: string implements HasLabel
{
    case FeePayment = 'fee_payment';
    case Salary = 'salary';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::FeePayment => 'Fee Payment',
            self::Salary => 'Salary',
            self::Other => 'Other',
        };
    }
}
