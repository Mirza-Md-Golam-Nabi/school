<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StudentIdCardValidity: string implements HasLabel
{
    case IssueDate = 'issue_date';
    case SessionEnd = 'session_end';

    public function getLabel(): string
    {
        return match ($this) {
            self::IssueDate => 'Issue date',
            self::SessionEnd => 'Valid till December of the session year',
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
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            self::IssueDate->value => 'স্থায়ী কার্ড — কার্ডে শুধু ইস্যুর তারিখ থাকে, মেয়াদ শেষের তারিখ থাকে না।',
            self::SessionEnd->value => 'বাৎসরিক কার্ড — প্রতি বছর নতুন করে বানাতে হয়।',
        ];
    }
}
