<?php

use App\Enums\ClassLevel;
use App\Models\Classes;
use App\Models\SchoolSetting;
use App\Support\AcademicSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('starts school sessions in January and college sessions in July by default', function () {
    expect(AcademicSession::startMonthFor(null))->toBe(1)
        ->and(AcademicSession::startMonthFor(ClassLevel::Secondary))->toBe(1)
        ->and(AcademicSession::startMonthFor(ClassLevel::College))->toBe(7);
});

it('resolves the session a date falls in for each level', function (string $date, ?ClassLevel $level, int $expected) {
    expect(AcademicSession::yearForDate(Carbon::parse($date), $level))->toBe($expected);
})->with([
    'school in February' => ['2027-02-10', ClassLevel::Secondary, 2027],
    'school in December' => ['2026-12-31', null, 2026],
    'college before July' => ['2027-02-10', ClassLevel::College, 2026],
    'college last day of June' => ['2027-06-30', ClassLevel::College, 2026],
    'college first day of July' => ['2027-07-01', ClassLevel::College, 2027],
    'college in December' => ['2026-12-01', ClassLevel::College, 2026],
]);

it('resolves the current session from today', function () {
    $this->travelTo(Carbon::parse('2027-03-15'));

    $college = Classes::create(['name' => 'Class 11', 'order' => 11, 'level' => ClassLevel::College]);
    $school = Classes::create(['name' => 'Class 9', 'order' => 9, 'level' => ClassLevel::Secondary]);

    expect($college->currentSessionYear())->toBe(2026)
        ->and($college->isCollege())->toBeTrue()
        ->and($school->currentSessionYear())->toBe(2027)
        ->and($school->isCollege())->toBeFalse();
});

it('follows the college start month from school settings', function () {
    SchoolSetting::set(AcademicSession::COLLEGE_START_MONTH_SETTING, '4');

    expect(AcademicSession::yearForDate(Carbon::parse('2027-03-31'), ClassLevel::College))->toBe(2026)
        ->and(AcademicSession::yearForDate(Carbon::parse('2027-04-01'), ClassLevel::College))->toBe(2027)
        ->and(AcademicSession::periodFor(2026, ClassLevel::College)['end']->toDateString())->toBe('2027-03-31');
});

it('falls back to July when the saved start month is invalid', function () {
    SchoolSetting::set(AcademicSession::COLLEGE_START_MONTH_SETTING, '13');

    expect(AcademicSession::collegeStartMonth())->toBe(7);
});

it('gives the first and last day of a session', function () {
    $school = AcademicSession::periodFor(2026);
    $college = AcademicSession::periodFor(2026, ClassLevel::College);

    expect($school['start']->toDateString())->toBe('2026-01-01')
        ->and($school['end']->toDateString())->toBe('2026-12-31')
        ->and($college['start']->toDateString())->toBe('2026-07-01')
        ->and($college['end']->toDateString())->toBe('2027-06-30');
});

it('labels college sessions across two years and school sessions as one year', function () {
    $college = Classes::create(['name' => 'Class 12', 'order' => 12, 'level' => ClassLevel::College]);

    expect(AcademicSession::label(2026))->toBe('2026')
        ->and(AcademicSession::label(2026, ClassLevel::College))->toBe('2026-27')
        ->and(AcademicSession::label(2099, ClassLevel::College))->toBe('2099-00')
        ->and($college->sessionLabel(2027))->toBe('2027-28');
});

it('offers session options newest first up to the next session', function () {
    $this->travelTo(Carbon::parse('2027-03-15'));

    expect(AcademicSession::optionsFor(ClassLevel::College))->toBe([2027 => '2027-28', 2026 => '2026-27'])
        ->and(AcademicSession::optionsFor(ClassLevel::Secondary))->toBe([2028 => '2028', 2027 => '2027', 2026 => '2026']);
});
