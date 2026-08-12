<?php

use App\Models\PublicHoliday;
use App\Models\SchoolSetting;
use App\Services\WorkingDaysCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Default: Friday is weekend
    SchoolSetting::set('weekend_days', json_encode(['friday']));
});

test('counts working days excluding weekends', function () {
    // Mon 2026-06-01 to Fri 2026-06-05 = 5 days, but Friday is weekend → 4 days
    $from = Carbon::parse('2026-06-01'); // Monday
    $to = Carbon::parse('2026-06-05');   // Friday

    $calculator = new WorkingDaysCalculator;

    expect($calculator->count($from, $to))->toBe(4);
});

test('counts working days with friday-saturday weekend', function () {
    SchoolSetting::set('weekend_days', json_encode(['friday', 'saturday']));

    // Mon 2026-06-01 to Sat 2026-06-06 = 6 days, Fri + Sat = 2 weekends → 4
    $from = Carbon::parse('2026-06-01');
    $to = Carbon::parse('2026-06-06');

    $calculator = new WorkingDaysCalculator;

    expect($calculator->count($from, $to))->toBe(4);
});

test('excludes public holidays from working days', function () {
    PublicHoliday::factory()->create([
        'start_date' => '2026-06-03',
        'is_recurring' => false,
    ]);

    // Mon-Thu 2026-06-01 to 2026-06-04 = 4 working days, but Wed 06-03 is holiday → 3
    $from = Carbon::parse('2026-06-01');
    $to = Carbon::parse('2026-06-04');

    $calculator = new WorkingDaysCalculator;

    expect($calculator->count($from, $to))->toBe(3);
});

test('single day that is a working day counts as 1', function () {
    $calculator = new WorkingDaysCalculator;
    $monday = Carbon::parse('2026-06-01');

    expect($calculator->count($monday, $monday))->toBe(1);
});

test('single day that is a weekend counts as 0', function () {
    $calculator = new WorkingDaysCalculator;
    $friday = Carbon::parse('2026-06-05'); // Friday = weekend

    expect($calculator->count($friday, $friday))->toBe(0);
});

test('getWorkingDays returns correct dates', function () {
    // Mon-Wed 2026-06-01 to 2026-06-04 (Thu) with Fri=weekend → 4 dates
    $from = Carbon::parse('2026-06-01');
    $to = Carbon::parse('2026-06-04');

    $calculator = new WorkingDaysCalculator;
    $days = $calculator->getWorkingDays($from, $to);

    expect($days)->toHaveCount(4)
        ->and($days->first()->format('Y-m-d'))->toBe('2026-06-01')
        ->and($days->last()->format('Y-m-d'))->toBe('2026-06-04');
});
