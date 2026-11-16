<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum InvoiceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Waived = 'waived';

    /** অনাদায়ী (খেলাপি) — student আর দেবে না; শুধু student fee invoice-এ ব্যবহৃত হয় */
    case Defaulted = 'defaulted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Partial => 'Partial',
            self::Paid => 'Paid',
            self::Waived => 'Waived',
            self::Defaulted => 'Defaulted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Waived => 'gray',
            self::Defaulted => 'danger',
        };
    }

    public function getIcon(): string|\BackedEnum|null
    {
        return match ($this) {
            self::Unpaid => Heroicon::OutlinedXCircle,
            self::Partial => Heroicon::OutlinedClock,
            self::Paid => Heroicon::OutlinedCheckCircle,
            self::Waived => Heroicon::OutlinedMinusCircle,
            self::Defaulted => Heroicon::OutlinedNoSymbol,
        };
    }

    /**
     * হাতে (form/filter থেকে) বেছে নেওয়া যায় এমন status — Defaulted শুধু
     * permission-যুক্ত action দিয়ে সেট হয়, আর salary invoice-এ প্রযোজ্যই নয়।
     * ইতিমধ্যে Defaulted record edit করার সময় সেটা option-এ রাখা হয় যাতে badge ঠিক দেখায়।
     *
     * @return array<string, string>
     */
    public static function selectableOptions(?self $current = null): array
    {
        return collect(self::cases())
            ->reject(fn (self $status): bool => $status === self::Defaulted && $current !== self::Defaulted)
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->getLabel()])
            ->all();
    }
}
