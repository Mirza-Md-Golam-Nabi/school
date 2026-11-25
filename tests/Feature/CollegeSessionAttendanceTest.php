<?php

use App\Actions\Attendance\ResolveStudentAttendanceTotals;
use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\SchoolSetting;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\AcademicSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->college = Classes::create(['name' => 'Class 11', 'order' => 11, 'level' => ClassLevel::College, 'is_active' => true]);
    $this->school = Classes::create(['name' => 'Class 9', 'order' => 9, 'level' => ClassLevel::Secondary, 'is_active' => true]);

    $this->collegeStudent = makeSessionAttendanceStudent($this->college, 2026);
    $this->schoolStudent = makeSessionAttendanceStudent($this->school, 2027);
});

function makeSessionAttendanceStudent(Classes $class, int $sessionYear): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

/**
 * @param  list<string>  $dates
 */
function markSessionAttendance(StudentProfile $student, Classes $class, array $dates): void
{
    foreach ($dates as $date) {
        Attendance::insert([
            'attendable_type' => StudentProfile::class,
            'attendable_id' => $student->id,
            'date' => $date,
            'status' => 'present',
            'source' => 'manual',
            'class_id' => $class->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

it('reads a session year per class: July to June for college, the calendar year for school', function () {
    markSessionAttendance($this->collegeStudent, $this->college, ['2026-06-30', '2026-07-01', '2027-03-15', '2027-06-30', '2027-07-01']);
    markSessionAttendance($this->schoolStudent, $this->school, ['2026-12-31', '2027-01-01', '2027-03-15', '2027-12-31']);

    $resolver = app(ResolveStudentAttendanceTotals::class);

    expect($resolver->devicePresentCounts(2026)->all())->toBe([$this->collegeStudent->id => 3, $this->schoolStudent->id => 1])
        ->and($resolver->devicePresentCounts(2027)->all())->toBe([$this->collegeStudent->id => 1, $this->schoolStudent->id => 3])
        ->and($resolver->deviceWorkingDays(2026, $this->college->id))->toBe(3);
});

it('counts each class on its own running session when no year is given', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));

    markSessionAttendance($this->collegeStudent, $this->college, ['2026-06-30', '2026-09-01', '2027-02-01']);
    markSessionAttendance($this->schoolStudent, $this->school, ['2026-12-31', '2027-02-01']);

    $resolver = app(ResolveStudentAttendanceTotals::class);

    expect($resolver->presentCountsForYear(null)->all())->toBe([$this->collegeStudent->id => 2, $this->schoolStudent->id => 1])
        ->and($resolver->presentCountsForYear(null, $this->college->id)->all())->toBe([$this->collegeStudent->id => 2])
        ->and($resolver->workingDaysForYear(null, $this->college->id))->toBe(2);
});

it('treats every class as a calendar-year class when the college session starts in January', function () {
    SchoolSetting::set(AcademicSession::COLLEGE_START_MONTH_SETTING, '1');

    markSessionAttendance($this->collegeStudent, $this->college, ['2026-06-30', '2026-09-01', '2027-02-01']);

    expect(Attendance::query()->inSession(2026)->count())->toBe(2);
});
