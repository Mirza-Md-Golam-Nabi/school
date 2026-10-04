<?php

use App\Actions\BuildEmployeeAttendanceReportData;
use App\Actions\BuildEmployeeAttendanceReportPdfAction;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Pages\StudentAttendanceReport;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\PublicHoliday;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->travelTo('2026-09-30 18:00:00');
    AttendanceSetting::current()->update(['entry_time' => '08:00:00', 'late_threshold_minutes' => 15]);
});

function employeeReportTeacher(string $name = 'Kamal Uddin', ?string $designation = 'Assistant Teacher'): TeacherProfile
{
    return TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'designation' => $designation,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

function employeeReportStaff(string $name = 'Jamal Mia'): StaffProfile
{
    return StaffProfile::create([
        'user_id' => User::factory()->create(['name' => $name])->id,
        'designation' => 'Office Assistant',
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

/**
 * Inserted raw so the stored date is a plain Y-m-d string (see StaffAttendanceTest).
 */
function employeeReportRow(object $person, string $date, AttendanceStatus $status, ?string $entry = null, ?string $exit = null): void
{
    Attendance::insert([
        'attendable_type' => $person::class,
        'attendable_id' => $person->getKey(),
        'date' => $date,
        'status' => $status->value,
        'source' => 'device',
        'entry_time' => $entry,
        'exit_time' => $exit,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function employeeReportAdmin(): User
{
    return grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
}

it('summarises a teacher\'s month and lists every day with entry, exit and minutes late', function () {
    $teacher = employeeReportTeacher();
    employeeReportRow($teacher, '2026-09-01', AttendanceStatus::Present, '07:55:00', '14:05:00');
    employeeReportRow($teacher, '2026-09-02', AttendanceStatus::Late, '08:47:00', '14:00:00');
    employeeReportRow($teacher, '2026-09-03', AttendanceStatus::Absent);
    employeeReportRow($teacher, '2026-09-06', AttendanceStatus::Leave);
    employeeReportRow($teacher, '2026-08-31', AttendanceStatus::Present, '08:00:00');
    employeeReportRow(employeeReportTeacher('Someone Else'), '2026-09-01', AttendanceStatus::Absent);

    $report = app(BuildEmployeeAttendanceReportData::class)->handle($teacher, 2026, 9);

    expect($report['name'])->toBe('Kamal Uddin')
        ->and($report['designation'])->toBe('Assistant Teacher')
        ->and($report['type'])->toBe('Teacher')
        // September 2026 has 30 days, 4 of them Fridays (the default weekend).
        ->and($report['summary'])->toBe(['working_days' => 26, 'present' => 2, 'late' => 1, 'absent' => 1, 'leave' => 1])
        ->and($report['days'])->toHaveCount(30);

    [$first, $second, $third, $friday] = [$report['days'][0], $report['days'][1], $report['days'][2], $report['days'][3]];

    expect($first['date']->toDateString())->toBe('2026-09-01')
        ->and($first['status'])->toBe(AttendanceStatus::Present)
        ->and($first['entry'])->toBe('07:55 AM')
        ->and($first['exit'])->toBe('02:05 PM')
        ->and($first['late_minutes'])->toBeNull()
        ->and($second['status'])->toBe(AttendanceStatus::Late)
        ->and($second['late_minutes'])->toBe(47)
        ->and($third['status'])->toBe(AttendanceStatus::Absent)
        ->and($third['entry'])->toBeNull()
        ->and($friday['date']->format('l'))->toBe('Friday')
        ->and($friday['is_working_day'])->toBeFalse()
        ->and($friday['status'])->toBeNull();
});

it('leaves a late present out of the present total when it is not counted for that group', function () {
    $staff = employeeReportStaff();
    employeeReportRow($staff, '2026-09-01', AttendanceStatus::Present, '07:55:00');
    employeeReportRow($staff, '2026-09-02', AttendanceStatus::Late, '08:30:00');

    $summary = fn (): array => app(BuildEmployeeAttendanceReportData::class)->handle($staff, 2026, 9)['summary'];

    expect($summary())->toMatchArray(['present' => 2, 'late' => 1]);

    AttendanceSetting::current()->update(['count_late_staff' => false]);

    expect($summary())->toMatchArray(['present' => 1, 'late' => 1]);
});

it('counts working days only up to today and skips public holidays', function () {
    $this->travelTo('2026-09-10 18:00:00');
    PublicHoliday::create(['name' => 'Special Holiday', 'start_date' => '2026-09-08', 'end_date' => '2026-09-08', 'is_recurring' => false]);
    $teacher = employeeReportTeacher();

    $report = app(BuildEmployeeAttendanceReportData::class)->handle($teacher, 2026, 9);

    // 1-10 September: ten days, minus one Friday (the 4th) and the holiday on the 8th.
    expect($report['summary']['working_days'])->toBe(8)
        ->and($report['days'][7]['is_working_day'])->toBeFalse()
        ->and(app(BuildEmployeeAttendanceReportData::class)->handle($teacher, 2026, 10)['summary']['working_days'])->toBe(0);
});

it('builds one pdf for several teachers and staff', function () {
    $pdf = app(BuildEmployeeAttendanceReportPdfAction::class)
        ->handle(collect([employeeReportTeacher(), employeeReportStaff()]), 2026, 9);

    expect($pdf)->toStartWith('%PDF');
});

it('streams the report as a download named after the person or the group', function () {
    $teacher = employeeReportTeacher('Kamal Uddin');
    $staff = employeeReportStaff();
    $this->actingAs(employeeReportAdmin());

    $single = $this->get(route('attendance-report.employees.download', ['year' => 2026, 'month' => 9, 'people' => ["teacher-{$teacher->id}"]]));

    $single->assertSuccessful()->assertHeader('Content-Type', 'application/pdf');
    expect($single->headers->get('Content-Disposition'))->toContain('attendance-report-Kamal-Uddin-September-2026.pdf');

    $several = $this->get(route('attendance-report.employees.download', ['year' => 2026, 'month' => 9, 'people' => ["teacher-{$teacher->id}", "staff-{$staff->id}"]]));

    $several->assertSuccessful();
    expect($several->headers->get('Content-Disposition'))->toContain('attendance-report-teachers-staff-September-2026.pdf');
});

it('rejects a download without valid people or month, and for unknown people', function (array $parameters, int $status) {
    $this->actingAs(employeeReportAdmin());

    $this->getJson(route('attendance-report.employees.download', $parameters))->assertStatus($status);
})->with([
    'nobody selected' => [['year' => 2026, 'month' => 9], 422],
    'malformed person' => [['year' => 2026, 'month' => 9, 'people' => ['student-1']], 422],
    'month out of range' => [['year' => 2026, 'month' => 13, 'people' => ['teacher-1']], 422],
    'unknown person' => [['year' => 2026, 'month' => 9, 'people' => ['teacher-999']], 404],
]);

it('only lets people who can use the admin panel download the report', function () {
    $teacher = employeeReportTeacher();
    $url = route('attendance-report.employees.download', ['year' => 2026, 'month' => 9, 'people' => ["teacher-{$teacher->id}"]]);

    $this->getJson($url)->assertUnauthorized();

    $this->actingAs($teacher->user)->get($url)->assertForbidden();
});

it('lists active teachers and staff in one searchable dropdown and sends the browser to the pdf', function () {
    $teacher = employeeReportTeacher('Kamal Uddin');
    $staff = employeeReportStaff('Jamal Mia');
    $resigned = employeeReportTeacher('Old Teacher');
    $resigned->update(['status' => EmploymentStatus::Resigned]);

    Livewire::actingAs(employeeReportAdmin());

    Livewire::test(StudentAttendanceReport::class)
        ->assertSchemaStateSet(['year' => 2026, 'month' => 9], 'employeeForm')
        ->assertSee('Kamal Uddin')
        ->assertSee('Jamal Mia')
        ->assertDontSee('Old Teacher')
        ->fillForm(['people' => ["teacher-{$teacher->id}", "staff-{$staff->id}"], 'month' => 8, 'year' => 2026], 'employeeForm')
        ->call('downloadEmployeeReport')
        ->assertHasNoFormErrors([], 'employeeForm')
        ->assertDispatched('download-attendance-reports', fn (string $name, array $params): bool => $params['urls'] === [
            route('attendance-report.employees.download', [
                'year' => 2026,
                'month' => 8,
                'people' => ["teacher-{$teacher->id}", "staff-{$staff->id}"],
            ]),
        ]);
});

it('requires at least one person before downloading', function () {
    Livewire::actingAs(employeeReportAdmin());

    Livewire::test(StudentAttendanceReport::class)
        ->call('downloadEmployeeReport')
        ->assertHasFormErrors(['people' => 'required'], 'employeeForm')
        ->assertNotDispatched('download-attendance-reports');
});
