<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\MyAttendance;
use App\Filament\Teacher\Widgets\TeacherAttendanceOverview;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTeacherAttendanceOverviewTeacher(): TeacherProfile
{
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    return TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

/**
 * Bypasses Eloquent's `date` cast, which appends a spurious time
 * component under SQLite and breaks exact-string date lookups.
 */
function markTeacherOverviewAttendance(TeacherProfile $teacher, string $date, string $status, ?string $entryTime = null): void
{
    Attendance::insert([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $teacher->id,
        'date' => $date,
        'status' => $status,
        'source' => 'manual',
        'entry_time' => $entryTime,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('shows today\'s attendance with entry time when the teacher has already marked it', function () {
    $teacher = createTeacherAttendanceOverviewTeacher();

    markTeacherOverviewAttendance($teacher, today()->subDays(3)->toDateString(), 'absent');
    markTeacherOverviewAttendance($teacher, today()->toDateString(), 'present', '09:15:00');

    $this->actingAs($teacher->user);

    $data = (new TeacherAttendanceOverview)->getViewData();

    expect($data['status']->value)->toBe('present')
        ->and($data['isToday'])->toBeTrue()
        ->and($data['date'])->toBe(today()->format('d M, Y'))
        ->and($data['time'])->toBe('09:15 AM')
        ->and($data['url'])->toBe(MyAttendance::getUrl(panel: 'teacher'));
});

it('falls back to the most recent past attendance when today has none', function () {
    $teacher = createTeacherAttendanceOverviewTeacher();

    markTeacherOverviewAttendance($teacher, today()->subDays(5)->toDateString(), 'late');
    markTeacherOverviewAttendance($teacher, today()->subDays(2)->toDateString(), 'leave');

    $this->actingAs($teacher->user);

    $data = (new TeacherAttendanceOverview)->getViewData();

    expect($data['status']->value)->toBe('leave')
        ->and($data['isToday'])->toBeFalse()
        ->and($data['date'])->toBe(today()->subDays(2)->format('d M, Y'));
});

it('shows nothing when the teacher has never marked attendance', function () {
    $teacher = createTeacherAttendanceOverviewTeacher();

    $this->actingAs($teacher->user);

    $data = (new TeacherAttendanceOverview)->getViewData();

    expect($data['status'])->toBeNull()
        ->and($data['date'])->toBeNull()
        ->and($data['time'])->toBeNull()
        ->and($data['isToday'])->toBeFalse();
});

it('renders the widget on the dashboard linking to the my-attendance page', function () {
    $teacher = createTeacherAttendanceOverviewTeacher();

    markTeacherOverviewAttendance($teacher, today()->toDateString(), 'present');

    $response = $this->actingAs($teacher->user)->get(route('filament.teacher.pages.dashboard'));

    $response->assertOk()
        ->assertSee("Today's Attendance")
        ->assertSee('href="'.MyAttendance::getUrl(panel: 'teacher').'"', false);
});
