<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\LateArrivalsReport;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Classes;
use App\Models\Permission;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->travelTo('2026-09-28 12:00:00');
    AttendanceSetting::current()->update(['entry_time' => '08:00:00', 'late_threshold_minutes' => 15]);
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
});

function lateReportStudent(string $className = 'Class 5', int $order = 5): StudentProfile
{
    $class = Classes::firstOrCreate(['name' => $className], ['order' => $order, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 3,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function lateReportRow(object $person, string $date, string $entryTime, AttendanceStatus $status = AttendanceStatus::Late): Attendance
{
    return Attendance::create([
        'attendable_type' => $person->getMorphClass(),
        'attendable_id' => $person->getKey(),
        'date' => $date,
        'class_id' => $person instanceof StudentProfile ? $person->current_class_id : null,
        'status' => $status,
        'source' => AttendanceSource::Device,
        'entry_time' => $entryTime,
    ]);
}

it('requires the view attendance permission', function () {
    $user = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($user);

    expect(LateArrivalsReport::canAccess())->toBeFalse();

    Permission::firstOrCreate(['name' => 'view_attendance', 'guard_name' => 'web']);
    $user->givePermissionTo('view_attendance');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(LateArrivalsReport::canAccess())->toBeTrue();
});

it('lists only late arrivals with who, when and how late', function () {
    $student = lateReportStudent();
    $teacher = TeacherProfile::factory()->create();
    $late = lateReportRow($student, '2026-09-21', '08:47:00');
    $teacherLate = lateReportRow($teacher, '2026-09-22', '09:05:00');
    $present = lateReportRow($student, '2026-09-23', '07:55:00', AttendanceStatus::Present);
    $absent = lateReportRow($student, '2026-09-24', '08:00:00', AttendanceStatus::Absent);

    Livewire::test(LateArrivalsReport::class)
        ->assertCanSeeTableRecords([$late, $teacherLate])
        ->assertCanNotSeeTableRecords([$present, $absent])
        ->assertTableColumnStateSet('attendable_type', 'Student', $late)
        ->assertTableColumnStateSet('attendable_type', 'Teacher', $teacherLate)
        ->assertTableColumnStateSet('person', $student->user->name.' (Class 5, Roll 3)', $late)
        ->assertTableColumnStateSet('person', $teacher->user->name, $teacherLate)
        ->assertTableColumnFormattedStateSet('entry_time', '08:47 AM', $late)
        ->assertTableColumnStateSet('minutes_late', '47 min', $late)
        ->assertTableColumnStateSet('minutes_late', '65 min', $teacherLate);
});

it('defaults to the current month and lets the range be changed', function () {
    $student = lateReportStudent();
    $thisMonth = lateReportRow($student, '2026-09-21', '08:30:00');
    $lastMonth = lateReportRow($student, '2026-08-20', '08:30:00');

    Livewire::test(LateArrivalsReport::class)
        ->assertCanSeeTableRecords([$thisMonth])
        ->assertCanNotSeeTableRecords([$lastMonth])
        ->filterTable('date_range', ['from' => '2026-08-01', 'until' => '2026-08-31'])
        ->assertCanSeeTableRecords([$lastMonth])
        ->assertCanNotSeeTableRecords([$thisMonth]);
});

it('filters by type and by class', function () {
    $fiveStudent = lateReportStudent('Class 5', 5);
    $sixStudent = lateReportStudent('Class 6', 6);
    $teacher = TeacherProfile::factory()->create();
    $five = lateReportRow($fiveStudent, '2026-09-21', '08:30:00');
    $six = lateReportRow($sixStudent, '2026-09-21', '08:30:00');
    $teacherRow = lateReportRow($teacher, '2026-09-21', '08:30:00');

    Livewire::test(LateArrivalsReport::class)
        ->filterTable('attendable_type', TeacherProfile::class)
        ->assertCanSeeTableRecords([$teacherRow])
        ->assertCanNotSeeTableRecords([$five, $six])
        ->removeTableFilter('attendable_type')
        ->filterTable('class_id', $fiveStudent->current_class_id)
        ->assertCanSeeTableRecords([$five])
        ->assertCanNotSeeTableRecords([$six, $teacherRow]);
});

it('searches by person name', function () {
    $first = lateReportStudent();
    $second = lateReportStudent();
    $firstRow = lateReportRow($first, '2026-09-21', '08:30:00');
    $secondRow = lateReportRow($second, '2026-09-21', '08:30:00');

    Livewire::test(LateArrivalsReport::class)
        ->searchTable($first->user->name)
        ->assertCanSeeTableRecords([$firstRow])
        ->assertCanNotSeeTableRecords([$secondRow]);
});
