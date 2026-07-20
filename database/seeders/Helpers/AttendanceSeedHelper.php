<?php

namespace Database\Seeders\Helpers;

use App\Models\SchoolSetting;
use Illuminate\Support\Carbon;

class AttendanceSeedHelper
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dateRange(): array
    {
        $today = Carbon::today();

        $from = in_array($today->month, [1, 2], true)
            ? $today->copy()->startOfYear()
            : $today->copy()->subMonths(2);

        return [$from, $today->copy()->subDay()];
    }

    public static function ensureWeekendDaysConfigured(): void
    {
        if (SchoolSetting::get('weekend_days') === null) {
            SchoolSetting::set('weekend_days', json_encode(['friday', 'saturday']));
        }
    }
}
