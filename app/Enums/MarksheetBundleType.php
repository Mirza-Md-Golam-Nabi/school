<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which students a ready-made combined marksheet PDF (MarksheetBundle) holds.
 */
enum MarksheetBundleType: string implements HasLabel
{
    case All = 'all';
    case Passed = 'passed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'সব student (পাস ও ফেল)',
            self::Passed => 'শুধু সব বিষয়ে পাশ করা student',
            self::Failed => 'শুধু ফেল করা student',
        };
    }

    /**
     * The BuildCombinedClassMarksheetPdfAction result filter for this bundle.
     */
    public function resultFilter(): ?string
    {
        return match ($this) {
            self::All => null,
            self::Passed => 'passed',
            self::Failed => 'failed',
        };
    }
}
