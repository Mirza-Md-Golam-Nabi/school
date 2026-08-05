<?php

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Pages\StaffAttendance;
use App\Filament\Pages\StaffAttendanceHistory;
use App\Models\Attendance;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAttendanceTestStaff(): StaffProfile
{
    return StaffProfile::create([
        'user_id' => User::factory()->create()->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

/**
 * Bypasses Eloquent's `date` cast (which appends a spurious time
 * component under SQLite, breaking exact-string `where('date', ...)`
 * lookups) by inserting the raw row directly, matching how the app's
 * own bulk seeders avoid the same quirk.
 */
function createTestAttendance(StaffProfile $staff, string $date, AttendanceStatus $status): void
{
    Attendance::insert([
        'attendable_type' => StaffProfile::class,
        'attendable_id' => $staff->id,
        'date' => $date,
        'status' => $status->value,
        'source' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('marks selected staff present and the rest absent', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $staff1 = createAttendanceTestStaff();
    $staff2 = createAttendanceTestStaff();

    Livewire::test(StaffAttendance::class)
        ->set('presentIds', [(string) $staff1->id])
        ->call('save');

    $record1 = Attendance::where('attendable_type', StaffProfile::class)->where('attendable_id', $staff1->id)->first();
    $record2 = Attendance::where('attendable_type', StaffProfile::class)->where('attendable_id', $staff2->id)->first();

    expect($record1->status)->toBe(AttendanceStatus::Present)
        ->and($record1->entry_time)->not->toBeNull()
        ->and($record2->status)->toBe(AttendanceStatus::Absent)
        ->and($record2->entry_time)->toBeNull();
});

it('loads existing attendance for the selected date', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $staff = createAttendanceTestStaff();

    createTestAttendance($staff, today()->toDateString(), AttendanceStatus::Present);

    Livewire::test(StaffAttendance::class)
        ->assertSet('presentIds', [(string) $staff->id]);
});

it('selects and deselects all staff', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $staff1 = createAttendanceTestStaff();
    $staff2 = createAttendanceTestStaff();

    Livewire::test(StaffAttendance::class)
        ->call('selectAll')
        ->assertSet('presentIds', [(string) $staff1->id, (string) $staff2->id])
        ->call('deselectAll')
        ->assertSet('presentIds', []);
});

it('renders the mark-attendance page with a link to history', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $response = $this->actingAs($admin)->get('/admin/staff-attendance');

    $response->assertOk();
    $response->assertSee('Staff');
    $response->assertSee('href="'.route('filament.admin.pages.staff-attendance-history').'"', false);
});

it('aggregates present and absent counts per day in the history page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $staff1 = createAttendanceTestStaff();
    $staff2 = createAttendanceTestStaff();

    createTestAttendance($staff1, today()->toDateString(), AttendanceStatus::Present);
    createTestAttendance($staff2, today()->toDateString(), AttendanceStatus::Absent);

    $history = Livewire::test(StaffAttendanceHistory::class)->instance()->getHistory();
    $today = $history->first(fn ($record) => Carbon::parse($record->date)->isSameDay(today()));

    expect($today->present_count)->toBe(1)
        ->and($today->absent_count)->toBe(1)
        ->and($today->total)->toBe(2);
});

it('renders the history page with a link back to the mark-attendance page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $response = $this->actingAs($admin)->get('/admin/staff-attendance-history');

    $response->assertOk();
    $response->assertSee('Staff Attendance History');
    $response->assertSee('href="'.route('filament.admin.pages.staff-attendance').'"', false);
});
