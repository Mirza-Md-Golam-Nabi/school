<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What absence in one paper of a merged subject means: the whole subject
 * fails, or the missed paper counts as 0 and the combined marks decide.
 */
enum MergedAbsentRule: string implements HasLabel
{
    case Fail = 'fail';
    case CountAsZero = 'count_as_zero';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fail => 'Fail — কোনো পত্রে অনুপস্থিত হলে বিষয়ে ফেল',
            self::CountAsZero => 'Count as 0 — অনুপস্থিত পত্রে ০ ধরে হিসাব',
        };
    }
}
