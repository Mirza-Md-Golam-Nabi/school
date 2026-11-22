<?php

use App\Actions\BuildTeacherAttendanceSheetPdfAction;
use App\Actions\BuildTeacherAttendanceSheetRows;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Pages\StudentAttendanceReport;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->travelTo('2026-09-30 18:00:00');
});

function sheetTeacher(string $name, EmploymentStatus $status = EmploymentStatus::Active): TeacherProfile
{
    return TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'designation' => 'Assistant Teacher',
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

/**
 * Inserted raw so the stored date is a plain Y-m-d string (see StaffAttendanceTest).
 */
function sheetAttendance(TeacherProfile $teacher, string $date, AttendanceStatus $status): void
{
    Attendance::insert([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $teacher->id,
        'date' => $date,
        'status' => $status->value,
        'source' => 'device',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('lists every active teacher by name with a P / LP / A / L mark per day and totals', function () {
    $zaman = sheetTeacher('Zaman Ali');
    $amina = sheetTeacher('Amina Begum');
    sheetTeacher('Former Teacher', EmploymentStatus::Terminated);

    sheetAttendance($amina, '2026-09-01', AttendanceStatus::Present);
    sheetAttendance($amina, '2026-09-02', AttendanceStatus::Late);
    sheetAttendance($amina, '2026-09-03', AttendanceStatus::Absent);
    sheetAttendance($amina, '2026-09-05', AttendanceStatus::Leave);
    sheetAttendance($zaman, '2026-09-01', AttendanceStatus::Late);

    ['rows' => $rows, 'days' => $days] = app(BuildTeacherAttendanceSheetRows::class)->handle(2026, 9);

    expect($days)->toHaveCount(30)
        ->and($days[4]['is_working_day'])->toBeFalse()
        ->and($days[4]['weekday'])->toBe('Fri')
        ->and($days[1]['is_working_day'])->toBeTrue()
        ->and($rows->pluck('name')->all())->toBe(['Amina Begum', 'Zaman Ali']);

    $aminaRow = $rows->first();

    expect($aminaRow['days'][1])->toBe('P')
        ->and($aminaRow['days'][2])->toBe('LP')
        ->and($aminaRow['days'][3])->toBe('A')
        ->and($aminaRow['days'][5])->toBe('L')
        ->and($aminaRow['days'][6])->toBe('')
        ->and($aminaRow['totals'])->toBe(['P' => 1, 'LP' => 1, 'A' => 1, 'L' => 1])
        ->and($rows->last()['totals'])->toBe(['P' => 0, 'LP' => 1, 'A' => 0, 'L' => 0]);
});

it('builds a valid pdf', function () {
    sheetTeacher('Amina Begum');

    expect(app(BuildTeacherAttendanceSheetPdfAction::class)->handle(2026, 9))->toStartWith('%PDF');
});

it('streams the sheet as a download for an admin', function () {
    sheetTeacher('Amina Begum');
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin)
        ->get(route('attendance-report.teachers.download', ['year' => 2026, 'month' => 9]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="teacher-attendance-sheet-September-2026.pdf"');
});

it('does not let a teacher download the sheet', function () {
    $teacher = sheetTeacher('Amina Begum');

    $this->actingAs($teacher->user)
        ->get(route('attendance-report.teachers.download', ['year' => 2026, 'month' => 9]))
        ->assertForbidden();
});

it('returns 404 when there are no active teachers', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin)
        ->get(route('attendance-report.teachers.download', ['year' => 2026, 'month' => 9]))
        ->assertNotFound();
});

it('sends the browser to the sheet for the chosen month from the report page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    Livewire::actingAs($admin);

    Livewire::test(StudentAttendanceReport::class)
        ->fillForm(['month' => 8, 'year' => 2026], 'teacherSheetForm')
        ->call('downloadTeacherSheet')
        ->assertDispatched('download-attendance-reports', urls: [
            route('attendance-report.teachers.download', ['year' => 2026, 'month' => 8]),
        ]);
});
