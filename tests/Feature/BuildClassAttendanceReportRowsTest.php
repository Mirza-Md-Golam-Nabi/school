<?php

use App\Actions\BuildClassAttendanceReportRows;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

function makeAttendanceReportTestStudent(int $classId, int $rollNo, StudentStatus $status = StudentStatus::Active): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true, 'name' => "Student Roll {$rollNo}"]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

function markAttendanceReportTestDay(StudentProfile $student, int $classId, string $date, AttendanceStatus $status): void
{
    Attendance::create([
        'attendable_type' => StudentProfile::class,
        'attendable_id' => $student->id,
        'class_id' => $classId,
        'date' => $date,
        'status' => $status,
        'source' => AttendanceSource::Manual,
    ]);
}

it('marks P for present and late, A for absent and leave, and blank for no record', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $student = makeAttendanceReportTestStudent($class->id, 1);

    markAttendanceReportTestDay($student, $class->id, '2026-03-01', AttendanceStatus::Present);
    markAttendanceReportTestDay($student, $class->id, '2026-03-02', AttendanceStatus::Late);
    markAttendanceReportTestDay($student, $class->id, '2026-03-03', AttendanceStatus::Absent);
    markAttendanceReportTestDay($student, $class->id, '2026-03-04', AttendanceStatus::Leave);
    // Day 5 left unmarked.

    ['rows' => $rows, 'daysInMonth' => $daysInMonth] = app(BuildClassAttendanceReportRows::class)->handle($class, 2026, 3);

    expect($daysInMonth)->toBe(31);

    $days = $rows->first()['days'];

    expect($days[1])->toBe('P')
        ->and($days[2])->toBe('P')
        ->and($days[3])->toBe('A')
        ->and($days[4])->toBe('A')
        ->and($days[5])->toBe('');
});

it('sorts students by roll no and includes only active students in the class', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $otherClass = Classes::create(['name' => 'Class Two', 'order' => 2]);

    makeAttendanceReportTestStudent($class->id, 3);
    makeAttendanceReportTestStudent($class->id, 1);
    makeAttendanceReportTestStudent($class->id, 2);
    makeAttendanceReportTestStudent($class->id, 4, StudentStatus::Graduated); // excluded
    makeAttendanceReportTestStudent($otherClass->id, 1); // different class, excluded

    ['rows' => $rows] = app(BuildClassAttendanceReportRows::class)->handle($class, 2026, 3);

    expect($rows->pluck('roll_no')->all())->toBe([1, 2, 3]);
});

it('only counts attendance from the requested class, month, and year', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $otherClass = Classes::create(['name' => 'Class Two', 'order' => 2]);
    $student = makeAttendanceReportTestStudent($class->id, 1);

    markAttendanceReportTestDay($student, $class->id, '2026-03-05', AttendanceStatus::Present);
    // Same day, different class — must not leak into this report.
    markAttendanceReportTestDay($student, $otherClass->id, '2026-03-05', AttendanceStatus::Absent);
    // Same day-of-month, different month — must not count.
    markAttendanceReportTestDay($student, $class->id, '2026-04-05', AttendanceStatus::Present);
    // Same month/day, previous year — must not count.
    markAttendanceReportTestDay($student, $class->id, '2025-03-05', AttendanceStatus::Present);

    ['rows' => $rows] = app(BuildClassAttendanceReportRows::class)->handle($class, 2026, 3);

    $days = $rows->first()['days'];

    expect($days[5])->toBe('P');

    foreach ($days as $day => $mark) {
        if ($day !== 5) {
            expect($mark)->toBe('');
        }
    }
});

it('aborts with 404 when the class has no active students', function () {
    $class = Classes::create(['name' => 'Empty Class', 'order' => 1]);

    expect(fn () => app(BuildClassAttendanceReportRows::class)->handle($class, 2026, 3))
        ->toThrow(NotFoundHttpException::class);
});
