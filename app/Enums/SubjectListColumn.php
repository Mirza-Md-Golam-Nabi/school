<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Subject List PDF-এ কোন কোন optional কলাম দেখানো যাবে (SL ও Name সবসময় থাকে)।
 */
enum SubjectListColumn: string implements HasLabel
{
    case NameBn = 'name_bn';
    case Code = 'code';
    case Written = 'has_written';
    case Mcq = 'has_mcq';
    case Practical = 'has_practical';
    case Status = 'is_active';

    public function getLabel(): string
    {
        return match ($this) {
            self::NameBn => 'Name (Bengali)',
            self::Code => 'Code',
            self::Written => 'Written',
            self::Mcq => 'MCQ',
            self::Practical => 'Practical',
            self::Status => 'Status',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }

    /**
     * ডিফল্টে সব কলাম সিলেক্ট থাকে।
     *
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return array_column(self::cases(), 'value');
    }
}
