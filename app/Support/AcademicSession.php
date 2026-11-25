<?php

namespace App\Support;

use App\Enums\ClassLevel;
use App\Models\Classes;
use App\Models\SchoolSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The single source of truth for academic sessions.
 *
 * A `session_year` is always the year the session STARTS in. School classes
 * run January–December, so session 2026 is Jan 2026 – Dec 2026. College
 * classes (11–12) start in the month set in School Settings (July by
 * default), so session 2026 is Jul 2026 – Jun 2027 and is shown as "2026-27".
 */
class AcademicSession
{
    public const COLLEGE_START_MONTH_SETTING = 'college_session_start_month';

    public const DEFAULT_COLLEGE_START_MONTH = 7;

    /**
     * The first year the system offers in session dropdowns.
     */
    public const FIRST_SESSION_YEAR = 2026;

    /**
     * The month (1–12) the class's session starts in.
     */
    public static function startMonthFor(Classes|ClassLevel|null $class): int
    {
        return self::isCollege($class) ? self::collegeStartMonth() : 1;
    }

    /**
     * The college session start month from School Settings, falling back to July.
     */
    public static function collegeStartMonth(): int
    {
        $month = (int) SchoolSetting::get(self::COLLEGE_START_MONTH_SETTING, self::DEFAULT_COLLEGE_START_MONTH);

        return $month >= 1 && $month <= 12 ? $month : self::DEFAULT_COLLEGE_START_MONTH;
    }

    /**
     * The session a date falls in — e.g. 2027-03-15 is session 2026 for a
     * college class but session 2027 for a school class.
     */
    public static function yearForDate(CarbonInterface $date, Classes|ClassLevel|null $class = null): int
    {
        return $date->month >= self::startMonthFor($class) ? $date->year : $date->year - 1;
    }

    /**
     * The class's session as of today.
     */
    public static function currentYearFor(Classes|ClassLevel|null $class = null): int
    {
        return self::yearForDate(now(), $class);
    }

    /**
     * The first and last day of a session.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public static function periodFor(int $year, Classes|ClassLevel|null $class = null): array
    {
        $start = CarbonImmutable::create($year, self::startMonthFor($class), 1)->startOfDay();

        return [
            'start' => $start,
            'end' => $start->addYear()->subDay()->endOfDay(),
        ];
    }

    /**
     * How a session is shown: "2026" when it fits in one calendar year,
     * "2026-27" when it runs into the next one.
     */
    public static function label(int $year, Classes|ClassLevel|null $class = null): string
    {
        if (self::startMonthFor($class) === 1) {
            return (string) $year;
        }

        return $year.'-'.substr((string) ($year + 1), -2);
    }

    /**
     * Session dropdown options, newest first, from the first session year up
     * to the session after the current one.
     *
     * @return array<int, string>
     */
    public static function optionsFor(Classes|ClassLevel|null $class = null, int $from = self::FIRST_SESSION_YEAR): array
    {
        $to = max($from, self::currentYearFor($class) + 1);

        return collect(range($to, $from))
            ->mapWithKeys(fn (int $year): array => [$year => self::label($year, $class)])
            ->all();
    }

    public static function isCollege(Classes|ClassLevel|null $class): bool
    {
        $level = $class instanceof Classes ? $class->level : $class;

        return $level === ClassLevel::College;
    }
}
