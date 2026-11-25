<?php

namespace App\Services;

use App\Models\PublicHoliday;
use App\Models\SchoolSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkingDaysCalculator
{
    /** @var array<int> Carbon day-of-week integers (0=Sun … 6=Sat) */
    private array $weekendDays;

    /** @var Collection<int, string> One-off holidays as 'Y-m-d', recurring ones as 'm-d' */
    private Collection $holidayDates;

    public function __construct()
    {
        $this->weekendDays = $this->resolveWeekendDays();
        $this->holidayDates = $this->resolveHolidayDates();
    }

    /**
     * Count working days between two dates (inclusive), excluding weekends and public holidays.
     */
    public function count(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($current->lte($end)) {
            if ($this->isWorkingDay($current)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Return all working day dates between two dates (inclusive).
     *
     * @return Collection<int, Carbon>
     */
    public function getWorkingDays(Carbon $from, Carbon $to): Collection
    {
        $dates = collect();
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($current->lte($end)) {
            if ($this->isWorkingDay($current)) {
                $dates->push($current->copy());
            }
            $current->addDay();
        }

        return $dates;
    }

    public function isWorkingDay(Carbon $date): bool
    {
        if (in_array($date->dayOfWeek, $this->weekendDays, true)) {
            return false;
        }

        return ! $this->holidayDates->contains($date->format('Y-m-d'))
            && ! $this->holidayDates->contains($date->format('m-d'));
    }

    /** @return array<int> */
    private function resolveWeekendDays(): array
    {
        $raw = SchoolSetting::get('weekend_days', '["friday"]');
        $names = json_decode($raw, true) ?? ['friday'];

        $map = [
            'sunday' => Carbon::SUNDAY,
            'monday' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
        ];

        return array_values(array_filter(
            array_map(fn (string $name) => $map[strtolower($name)] ?? null, $names),
            fn ($v) => $v !== null,
        ));
    }

    /**
     * One-off holidays as 'Y-m-d', recurring ones as 'm-d' — so a recurring
     * holiday falls on its day in every year, not only the current one (date
     * ranges and sessions can span two calendar years).
     *
     * @return Collection<int, string>
     */
    private function resolveHolidayDates(): Collection
    {
        return PublicHoliday::query()->get(['id', 'start_date', 'end_date', 'is_recurring'])->flatMap(function (PublicHoliday $holiday): array {
            $format = $holiday->is_recurring ? 'm-d' : 'Y-m-d';
            $current = $holiday->start_date->copy();
            $end = $holiday->end_date ?? $holiday->start_date;
            $dates = [];

            while ($current->lte($end)) {
                $dates[] = $current->format($format);
                $current->addDay();
            }

            return $dates;
        })->unique()->values();
    }
}
