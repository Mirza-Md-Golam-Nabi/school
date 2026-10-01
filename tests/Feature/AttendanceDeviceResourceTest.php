<?php

use App\Actions\Attendance\AssignDeviceEnrollIdsAction;
use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\AttendanceDevices\AttendanceDeviceResource;
use App\Filament\Resources\AttendanceDevices\Pages\CreateAttendanceDevice;
use App\Filament\Resources\AttendanceDevices\Pages\EditAttendanceDevice;
use App\Filament\Resources\AttendanceDevices\Pages\ListAttendanceDevices;
use App\Filament\Resources\AttendanceDevices\RelationManagers\DeviceUsersRelationManager;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\Permission;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['attendance.auto_enroll' => false]);

    Filament::setCurrentPanel('admin');
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
});

function deviceUiStudent(Classes $class, int $rollNo, StudentStatus $status = StudentStatus::Active): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

/**
 * The plain API token shown to the admin lives only in the flashed notification.
 */
function deviceUiFlashedToken(): string
{
    $notifications = new Notifications;
    $notifications->mount();

    return (string) $notifications->notifications
        ->first(fn ($notification): bool => str_contains((string) $notification->getTitle(), 'token'))
        ?->getBody();
}

function deviceUiRelationManager(AttendanceDevice $device)
{
    return Livewire::test(DeviceUsersRelationManager::class, [
        'ownerRecord' => $device,
        'pageClass' => EditAttendanceDevice::class,
    ]);
}

it('is only accessible to users who may manage attendance devices', function () {
    $user = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    test()->actingAs($user);

    expect(AttendanceDeviceResource::canAccess())->toBeFalse();

    Permission::firstOrCreate(['name' => 'manage_attendance_devices', 'guard_name' => 'web']);
    $user->givePermissionTo('manage_attendance_devices');
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(AttendanceDeviceResource::canAccess())->toBeTrue();
});

it('lists devices with their enrolled and unmatched punch counts', function () {
    $device = AttendanceDevice::factory()->create();
    AttendancePunch::factory()->count(2)->sequence(
        ['enroll_id' => '900', 'punched_at' => '2026-09-28 08:00:00'],
        ['enroll_id' => '901', 'punched_at' => '2026-09-28 08:01:00'],
    )->create(['attendance_device_id' => $device->id]);

    Livewire::test(ListAttendanceDevices::class)
        ->assertCanSeeTableRecords([$device])
        ->assertTableColumnStateSet('unprocessed_punches_count', 2, $device)
        ->assertTableColumnStateSet('device_users_count', 0, $device);
});

it('creates a device and shows its API token exactly once', function () {
    Livewire::test(CreateAttendanceDevice::class)
        ->fillForm(['name' => 'Main Gate K40', 'serial_number' => 'CKPG123'])
        ->call('create')
        ->assertHasNoFormErrors();

    $device = AttendanceDevice::where('name', 'Main Gate K40')->sole();
    $token = deviceUiFlashedToken();

    expect($device->api_token_hash)->not->toBe($token)
        ->and(AttendanceDevice::findActiveByToken($token)?->is($device))->toBeTrue()
        ->and($device->is_active)->toBeTrue();
});

it('validates the device form', function () {
    AttendanceDevice::factory()->create(['serial_number' => 'DUPLICATE']);

    Livewire::test(CreateAttendanceDevice::class)
        ->fillForm(['name' => '', 'serial_number' => 'DUPLICATE'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'serial_number' => 'unique']);
});

it('regenerates the API token so the old one stops working', function () {
    $device = AttendanceDevice::factory()->withToken('old-token')->create();

    Livewire::test(ListAttendanceDevices::class)
        ->callAction(TestAction::make('regenerateToken')->table($device));

    $newToken = deviceUiFlashedToken();

    expect(AttendanceDevice::findActiveByToken('old-token'))->toBeNull()
        ->and(AttendanceDevice::findActiveByToken($newToken)?->is($device))->toBeTrue();
});

it('processes pending punches from the table', function () {
    Notification::fake();
    $this->travelTo('2026-09-28 08:30:00');
    AttendanceSetting::current()->update(['attendance_mode' => AttendanceMode::Daily]);

    $device = AttendanceDevice::factory()->create();
    $student = deviceUiStudent(Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]), 1);
    DeviceUser::factory()->forPerson($student)->create(['attendance_device_id' => $device->id, 'enroll_id' => '7']);
    AttendancePunch::factory()->create(['attendance_device_id' => $device->id, 'enroll_id' => '7', 'punched_at' => '2026-09-28 08:05:00']);

    Livewire::test(ListAttendanceDevices::class)
        ->callAction(TestAction::make('processPunches')->table($device))
        ->assertNotified();

    expect(Attendance::where('attendable_id', $student->id)->sole()->status)->toBe(AttendanceStatus::Present);
});

it('links an enroll id to a student and processes punches that were waiting', function () {
    Notification::fake();
    $this->travelTo('2026-09-28 08:30:00');
    AttendanceSetting::current()->update(['attendance_mode' => AttendanceMode::Daily]);

    $device = AttendanceDevice::factory()->create();
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $student = deviceUiStudent($class, 1);
    AttendancePunch::factory()->create(['attendance_device_id' => $device->id, 'enroll_id' => '55', 'punched_at' => '2026-09-28 08:05:00']);

    deviceUiRelationManager($device)
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'person_type' => StudentProfile::class,
            'class_id' => $class->id,
            'person_id' => $student->id,
            'enroll_id' => '55',
        ])
        ->assertHasNoFormErrors();

    $link = DeviceUser::where('attendance_device_id', $device->id)->sole();

    expect($link->enroll_id)->toBe('55')
        ->and($link->enrollable_type)->toBe(StudentProfile::class)
        ->and($link->enrollable_id)->toBe($student->id)
        ->and(Attendance::where('attendable_id', $student->id)->count())->toBe(1);
});

it('only lists students of the chosen class in the Link Enroll ID person dropdown', function () {
    $classFive = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $classSix = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $fiveStudent = deviceUiStudent($classFive, 1);
    $sixStudent = deviceUiStudent($classSix, 1);

    // searchPeople() is a private method backing the person_id select's
    // getSearchResultsUsing(); it doesn't touch component/mount state, so it's
    // reachable directly by reflection without a full Livewire mount.
    $manager = new DeviceUsersRelationManager;
    $search = new ReflectionMethod($manager, 'searchPeople');

    expect($search->invoke($manager, StudentProfile::class, ''))->toBe([]);

    $filtered = $search->invoke($manager, StudentProfile::class, '', $classFive->id);

    expect($filtered)->toHaveKey((string) $fiveStudent->id)
        ->not->toHaveKey((string) $sixStudent->id);
});

it('leaves the class field hidden and the person field enabled for teachers and staff', function () {
    $device = AttendanceDevice::factory()->create();
    TeacherProfile::factory()->create();

    deviceUiRelationManager($device)
        ->mountTableAction('create')
        ->set('mountedActions.0.data.person_type', TeacherProfile::class)
        ->assertFormFieldIsHidden('class_id')
        ->assertFormFieldIsEnabled('person_id');
});

it('rejects a duplicate enroll id or a person who is already linked', function () {
    $device = AttendanceDevice::factory()->create();
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $first = deviceUiStudent($class, 1);
    $second = deviceUiStudent($class, 2);
    DeviceUser::factory()->forPerson($first)->create(['attendance_device_id' => $device->id, 'enroll_id' => '1']);

    deviceUiRelationManager($device)
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'person_type' => StudentProfile::class,
            'person_id' => $second->id,
            'enroll_id' => '1',
        ])
        ->assertHasFormErrors(['enroll_id']);

    deviceUiRelationManager($device)
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'person_type' => StudentProfile::class,
            'person_id' => $first->id,
            'enroll_id' => '2',
        ])
        ->assertHasFormErrors(['person_id']);

    expect(DeviceUser::count())->toBe(1);
});

it('allows the same enroll id on a different device', function () {
    $otherDevice = AttendanceDevice::factory()->create();
    $device = AttendanceDevice::factory()->create();
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $student = deviceUiStudent($class, 1);
    DeviceUser::factory()->forPerson($student)->create(['attendance_device_id' => $otherDevice->id, 'enroll_id' => '1']);

    deviceUiRelationManager($device)
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'person_type' => StudentProfile::class,
            'class_id' => $class->id,
            'person_id' => $student->id,
            'enroll_id' => '1',
        ])
        ->assertHasNoFormErrors();

    expect(DeviceUser::where('attendance_device_id', $device->id)->count())->toBe(1);
});

it('lets an admin change an enroll id', function () {
    $device = AttendanceDevice::factory()->create();
    $student = deviceUiStudent(Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]), 1);
    $link = DeviceUser::factory()->forPerson($student)->create(['attendance_device_id' => $device->id, 'enroll_id' => '1']);

    deviceUiRelationManager($device)
        ->callAction(TestAction::make(EditAction::class)->table($link), ['enroll_id' => '77'])
        ->assertHasNoFormErrors();

    expect($link->fresh()->enroll_id)->toBe('77');
});

it('shows who each enroll id belongs to', function () {
    $device = AttendanceDevice::factory()->create();
    $student = deviceUiStudent(Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]), 3);
    $teacher = TeacherProfile::factory()->create();
    $studentLink = DeviceUser::factory()->forPerson($student)->create(['attendance_device_id' => $device->id, 'enroll_id' => '1']);
    $teacherLink = DeviceUser::factory()->forPerson($teacher)->create(['attendance_device_id' => $device->id, 'enroll_id' => '2']);

    deviceUiRelationManager($device)
        ->assertCanSeeTableRecords([$studentLink, $teacherLink])
        ->assertTableColumnStateSet('enrollable_type', 'Student', $studentLink)
        ->assertTableColumnStateSet('enrollable_type', 'Teacher', $teacherLink)
        ->assertTableColumnStateSet('person', $student->user->name.' (Class 5, Roll 3)', $studentLink)
        ->assertTableColumnStateSet('person', $teacher->user->name, $teacherLink)
        ->searchTable($student->user->name)
        ->assertCanSeeTableRecords([$studentLink])
        ->assertCanNotSeeTableRecords([$teacherLink]);
});

it('filters enrolled people by class, leaving teachers and staff out of any class result', function () {
    $device = AttendanceDevice::factory()->create();
    $classFive = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $classSix = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $fiveStudent = deviceUiStudent($classFive, 1);
    $sixStudent = deviceUiStudent($classSix, 1);
    $teacher = TeacherProfile::factory()->create();
    $fiveLink = DeviceUser::factory()->forPerson($fiveStudent)->create(['attendance_device_id' => $device->id, 'enroll_id' => '1']);
    $sixLink = DeviceUser::factory()->forPerson($sixStudent)->create(['attendance_device_id' => $device->id, 'enroll_id' => '2']);
    $teacherLink = DeviceUser::factory()->forPerson($teacher)->create(['attendance_device_id' => $device->id, 'enroll_id' => '3']);

    deviceUiRelationManager($device)
        ->assertCanSeeTableRecords([$fiveLink, $sixLink, $teacherLink])
        ->filterTable('class_id', $classFive->id)
        ->assertCanSeeTableRecords([$fiveLink])
        ->assertCanNotSeeTableRecords([$sixLink, $teacherLink]);
});

it('auto-assigns sequential enroll ids to every active person not yet linked', function () {
    $device = AttendanceDevice::factory()->create();
    $classSix = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $classFive = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $sixRollOne = deviceUiStudent($classSix, 1);
    $fiveRollTwo = deviceUiStudent($classFive, 2);
    $fiveRollOne = deviceUiStudent($classFive, 1);
    $dropped = deviceUiStudent($classFive, 3, StudentStatus::Dropped);
    $alreadyLinked = deviceUiStudent($classFive, 4);
    $teacher = TeacherProfile::factory()->create();
    $resignedTeacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Resigned]);
    $staff = StaffProfile::factory()->create();

    DeviceUser::factory()->forPerson($alreadyLinked)->create(['attendance_device_id' => $device->id, 'enroll_id' => '10']);

    $assigned = app(AssignDeviceEnrollIdsAction::class)->handle($device, array_keys(DeviceUser::personTypes()));

    $enrollIdOf = fn ($person): ?string => DeviceUser::where('attendance_device_id', $device->id)
        ->where('enrollable_type', $person->getMorphClass())
        ->where('enrollable_id', $person->getKey())
        ->value('enroll_id');

    expect($assigned)->toBe(5)
        ->and($enrollIdOf($alreadyLinked))->toBe('10')
        ->and($enrollIdOf($fiveRollOne))->toBe('11')
        ->and($enrollIdOf($fiveRollTwo))->toBe('12')
        ->and($enrollIdOf($sixRollOne))->toBe('13')
        ->and($enrollIdOf($teacher))->toBe('14')
        ->and($enrollIdOf($staff))->toBe('15')
        ->and($enrollIdOf($dropped))->toBeNull()
        ->and($enrollIdOf($resignedTeacher))->toBeNull();

    expect(app(AssignDeviceEnrollIdsAction::class)->handle($device, array_keys(DeviceUser::personTypes())))->toBe(0);
});

it('only auto-assigns the chosen kinds of people from the relation manager', function () {
    $device = AttendanceDevice::factory()->create();
    $student = deviceUiStudent(Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]), 1);
    $teacher = TeacherProfile::factory()->create();

    deviceUiRelationManager($device)
        ->callAction(TestAction::make('assignEnrollIds')->table(), ['person_types' => [TeacherProfile::class]])
        ->assertNotified();

    expect(DeviceUser::where('attendance_device_id', $device->id)->pluck('enrollable_type')->all())->toBe([$teacher->getMorphClass()])
        ->and($student->deviceEnrollments()->count())->toBe(0);
});

it('saves the capacity limits entered in the device information', function () {
    Livewire::test(CreateAttendanceDevice::class)
        ->fillForm([
            'name' => 'Main Gate K40',
            'user_capacity' => 1000,
            'fingerprint_capacity' => 3000,
            'card_capacity' => 3000,
            'record_capacity' => 100000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $device = AttendanceDevice::where('name', 'Main Gate K40')->sole();

    expect($device->user_capacity)->toBe(1000)
        ->and($device->fingerprint_capacity)->toBe(3000)
        ->and($device->card_capacity)->toBe(3000)
        ->and($device->record_capacity)->toBe(100000);

    Livewire::test(EditAttendanceDevice::class, ['record' => $device->id])
        ->assertSchemaStateSet(['user_capacity' => 1000, 'record_capacity' => 100000])
        ->fillForm(['user_capacity' => 1500])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($device->fresh()->user_capacity)->toBe(1500);
});

it('leaves the capacity limits optional', function () {
    Livewire::test(CreateAttendanceDevice::class)
        ->fillForm(['name' => 'No Limits Yet'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AttendanceDevice::where('name', 'No Limits Yet')->sole()->user_capacity)->toBeNull();
});

it('rejects capacity limits that are not positive whole numbers', function (mixed $value) {
    Livewire::test(CreateAttendanceDevice::class)
        ->fillForm(['name' => 'Bad Limits', 'user_capacity' => $value])
        ->call('create')
        ->assertHasFormErrors(['user_capacity']);
})->with([
    'zero' => 0,
    'negative' => -5,
    'decimal' => 10.5,
    'text' => 'lots',
]);

it('saves after how many days the device log is cleared and leaves it optional', function () {
    Livewire::test(CreateAttendanceDevice::class)
        ->fillForm(['name' => 'Main Gate K40', 'log_retention_days' => 30])
        ->call('create')
        ->assertHasNoFormErrors();

    $device = AttendanceDevice::where('name', 'Main Gate K40')->sole();

    expect($device->log_retention_days)->toBe(30);

    Livewire::test(EditAttendanceDevice::class, ['record' => $device->id])
        ->assertSchemaStateSet(['log_retention_days' => 30])
        ->fillForm(['log_retention_days' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($device->fresh()->log_retention_days)->toBeNull();
});

it('rejects a device log retention that is not a sensible number of days', function (mixed $value) {
    Livewire::test(CreateAttendanceDevice::class)
        ->fillForm(['name' => 'Bad Retention', 'log_retention_days' => $value])
        ->call('create')
        ->assertHasFormErrors(['log_retention_days']);
})->with([
    'zero' => 0,
    'negative' => -5,
    'decimal' => 10.5,
    'too long' => 3651,
]);

it('shows only the used user count until a capacity is entered', function () {
    $noLimit = AttendanceDevice::factory()->create(['reported_sizes' => ['users' => 40], 'sizes_reported_at' => now()]);
    $withLimit = AttendanceDevice::factory()->create(['user_capacity' => 1000, 'reported_sizes' => ['users' => 40], 'sizes_reported_at' => now()]);
    $notReported = AttendanceDevice::factory()->create();

    Livewire::test(ListAttendanceDevices::class)
        ->assertTableColumnStateSet('capacity', '40', $noLimit)
        ->assertTableColumnStateSet('capacity', '40 / 1000', $withLimit)
        ->assertTableColumnStateSet('capacity', null, $notReported);
});
