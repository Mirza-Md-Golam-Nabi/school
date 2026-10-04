<?php

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\ManageAttendanceSettings;
use App\Filament\Pages\StaffAttendance;
use App\Filament\Pages\StaffAttendanceHistory;
use App\Filament\Pages\StudentAttendanceRanking;
use App\Filament\Widgets\StaffAttendanceOverviewWidget;
use App\Filament\Widgets\StudentAttendanceOverviewWidget;
use App\Filament\Widgets\TeacherAttendanceOverviewWidget;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Classes;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
});

function countLateStaff(): StaffProfile
{
    return StaffProfile::create([
        'user_id' => User::factory()->create()->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

function countLateStudent(int $roll): StudentProfile
{
    $class = Classes::firstOrCreate(['name' => 'Class 5'], ['order' => 5, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $roll,
        'current_class_id' => $class->id,
        'session_year' => (int) now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

/**
 * Inserted raw so the stored date is a plain Y-m-d string (see StaffAttendanceTest).
 */
function countLateRow(object $person, AttendanceStatus $status, ?string $date = null): void
{
    Attendance::insert([
        'attendable_type' => $person::class,
        'attendable_id' => $person->getKey(),
        'date' => $date ?? today()->toDateString(),
        'class_id' => $person instanceof StudentProfile ? $person->current_class_id : null,
        'status' => $status->value,
        'source' => 'device',
        'entry_time' => '09:30:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('counts a late present as present for everyone until a box is unticked', function () {
    $setting = AttendanceSetting::current();

    expect($setting->countsLateFor(StudentProfile::class))->toBeTrue()
        ->and($setting->countsLateFor(TeacherProfile::class))->toBeTrue()
        ->and($setting->countsLateFor(StaffProfile::class))->toBeTrue()
        ->and($setting->presentStatusesFor(StudentProfile::class))->toBe([AttendanceStatus::Present, AttendanceStatus::Late])
        ->and($setting->presentStatusValuesFor(TeacherProfile::class))->toBe(['present', 'late'])
        ->and(AttendanceSetting::sole()->count_late_staff)->toBeTrue();

    $setting->update(['count_late_teachers' => false]);

    expect($setting->presentStatusesFor(TeacherProfile::class))->toBe([AttendanceStatus::Present])
        ->and($setting->presentStatusesFor(StudentProfile::class))->toBe([AttendanceStatus::Present, AttendanceStatus::Late])
        ->and($setting->presentStatusesFor(StaffProfile::class))->toBe([AttendanceStatus::Present, AttendanceStatus::Late]);
});

it('saves a separate tick for students, teachers and staff on the settings page', function () {
    AttendanceSetting::current();

    Livewire::test(ManageAttendanceSettings::class)
        ->assertSchemaStateSet(['count_late_students' => true, 'count_late_teachers' => true, 'count_late_staff' => true])
        ->fillForm(['count_late_teachers' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AttendanceSetting::current())
        ->count_late_students->toBeTrue()
        ->count_late_teachers->toBeFalse()
        ->count_late_staff->toBeTrue();
});

it('counts late arrivals in each dashboard total only for the ticked kind of person', function () {
    $student = countLateStudent(1);
    $teacher = TeacherProfile::factory()->create();
    $staff = countLateStaff();

    foreach ([$student, $teacher, $staff] as $person) {
        countLateRow($person, AttendanceStatus::Late);
    }

    countLateRow(countLateStudent(2), AttendanceStatus::Present);

    AttendanceSetting::current()->update(['count_late_students' => false, 'count_late_teachers' => false, 'count_late_staff' => false]);

    $presentToday = fn (): array => [
        app(StudentAttendanceOverviewWidget::class)->getViewData()['presentToday'],
        app(TeacherAttendanceOverviewWidget::class)->getViewData()['presentToday'],
        app(StaffAttendanceOverviewWidget::class)->getViewData()['presentToday'],
    ];

    expect($presentToday())->toEqual([1, 0, 0]);

    AttendanceSetting::current()->update(['count_late_students' => true, 'count_late_staff' => true]);

    expect($presentToday())->toEqual([2, 0, 1]);

    AttendanceSetting::current()->update(['count_late_teachers' => true]);

    expect($presentToday())->toEqual([2, 1, 1]);
});

it('counts late days in the student ranking only while student late arrivals are counted', function () {
    $punctual = countLateStudent(1);
    $oftenLate = countLateStudent(2);
    $year = now()->year;

    countLateRow($punctual, AttendanceStatus::Present, "{$year}-01-05");
    countLateRow($punctual, AttendanceStatus::Present, "{$year}-01-06");
    countLateRow($oftenLate, AttendanceStatus::Present, "{$year}-01-05");
    countLateRow($oftenLate, AttendanceStatus::Late, "{$year}-01-06");
    countLateRow($oftenLate, AttendanceStatus::Late, "{$year}-01-07");

    AttendanceSetting::current()->update(['count_late_students' => false, 'count_late_teachers' => false, 'count_late_staff' => false]);

    $counts = fn (): array => app(StudentAttendanceRanking::class)->getTopStudents()->pluck('present_count', 'roll_no')->all();

    expect($counts())->toEqual([1 => 2, 2 => 1])
        ->and(Attendance::query()->countedPresent(StudentProfile::class)->count())->toBe(3);

    AttendanceSetting::current()->update(['count_late_students' => true]);

    expect($counts())->toEqual([2 => 3, 1 => 2])
        ->and(Attendance::query()->countedPresent(StudentProfile::class)->count())->toBe(5);
});

it('adds counted late arrivals to the present total of the daily history', function () {
    countLateRow(countLateStaff(), AttendanceStatus::Present);
    countLateRow(countLateStaff(), AttendanceStatus::Late);
    countLateRow(countLateStaff(), AttendanceStatus::Absent);

    AttendanceSetting::current()->update(['count_late_students' => false, 'count_late_teachers' => false, 'count_late_staff' => false]);

    $today = fn (): object => app(StaffAttendanceHistory::class)->getHistory()->first();

    expect((int) $today()->present_count)->toBe(1)
        ->and((int) $today()->absent_count)->toBe(1);

    AttendanceSetting::current()->update(['count_late_staff' => true]);

    expect((int) $today()->present_count)->toBe(2)
        ->and((int) $today()->absent_count)->toBe(1)
        ->and((int) $today()->total)->toBe(3);
});

it('shows a late present as ticked on the marking page and keeps it late present when saved', function () {
    $late = countLateStaff();
    $absent = countLateStaff();
    countLateRow($late, AttendanceStatus::Late);

    Livewire::test(StaffAttendance::class)
        ->assertSet('presentIds', [(string) $late->id])
        ->call('save');

    $row = Attendance::where('attendable_type', StaffProfile::class)->where('attendable_id', $late->id)->sole();

    expect($row->status)->toBe(AttendanceStatus::Late)
        ->and($row->entry_time)->toBe('09:30:00')
        ->and(Attendance::where('attendable_id', $absent->id)->sole()->status)->toBe(AttendanceStatus::Absent);
});

it('leaves an uncounted late present unticked on the marking page', function () {
    AttendanceSetting::current()->update(['count_late_staff' => false]);
    $late = countLateStaff();
    countLateRow($late, AttendanceStatus::Late);

    Livewire::test(StaffAttendance::class)->assertSet('presentIds', []);
});
