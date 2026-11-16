<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\CreateStudentProfile;
use App\Filament\Resources\StudentProfiles\Pages\EditStudentProfile;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Models\Address;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('prefills the class field when classId is passed as a query parameter', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    $response = $this->actingAs($admin)->get("/admin/student-profiles/create?classId={$class->id}");

    $response->assertOk();
    $response->assertSee('&quot;current_class_id&quot;:'.$class->id, false);
});

it('leaves the class field empty when no classId is passed', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    $response = $this->actingAs($admin)->get('/admin/student-profiles/create');

    $response->assertOk();
    $response->assertDontSee('&quot;current_class_id&quot;:'.$class->id, false);
});

it('does not show the student id field on create, since it is system-generated', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    Livewire::test(CreateStudentProfile::class)
        ->assertFormFieldIsHidden('student_id_no');
});

it('shows the existing student id when a matching birth certificate no is entered on create', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);
    $existingUser = User::factory()->create(['email' => 'std00001@school.com']);
    StudentProfile::create([
        'user_id' => $existingUser->id,
        'roll_no' => 1,
        'birth_certificate_no' => '1111111111',
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm(['birth_certificate_no' => '1111111111'])
        ->assertFormSet(['student_id_no' => StudentProfile::where('birth_certificate_no', '1111111111')->value('student_id_no')]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm(['birth_certificate_no' => '9999999999'])
        ->assertFormSet(['student_id_no' => null]);
});

it('replaces the matching profile in place instead of creating a duplicate when the birth certificate no already exists', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $oldClass = Classes::create(['name' => 'Class 5', 'order' => 5]);
    $newClass = Classes::create(['name' => 'Class 6', 'order' => 6]);

    $existingUser = User::factory()->create(['name' => 'Old Name', 'email' => 'std00001@school.com', 'pin' => '1234', 'must_change_password' => false]);
    $existingProfile = StudentProfile::create([
        'user_id' => $existingUser->id,
        'roll_no' => 1,
        'birth_certificate_no' => '1111111111',
        'current_class_id' => $oldClass->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'name' => 'Updated Name',
            'roll_no' => 9,
            'birth_certificate_no' => '1111111111',
            'session_year' => now()->year,
            'current_class_id' => $newClass->id,
            'gender' => 'male',
            'nationality' => 'Bangladeshi',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(StudentProfile::where('birth_certificate_no', '1111111111')->count())->toBe(1);

    $existingProfile->refresh();
    $refreshedUser = $existingUser->fresh();

    expect($existingProfile->roll_no)->toBe(9)
        ->and($existingProfile->current_class_id)->toBe($newClass->id)
        ->and($refreshedUser->name)->toBe('Updated Name')
        // Same account, same email — no duplicate login is created.
        ->and($refreshedUser->email)->toBe('std00001@school.com')
        // Password is reset to the default and must be changed again on next login.
        ->and(Hash::check('password', $refreshedUser->password))->toBeTrue()
        ->and($refreshedUser->must_change_password)->toBeTrue()
        ->and($refreshedUser->pin)->toBeNull();

    expect(session('generated_student_credentials'))->toBe([
        'student_id' => $existingProfile->student_id_no,
        'password' => 'password',
    ]);
});

it('still generates a new email when birth certificate no is left blank', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'name' => 'No Birth Cert Student',
            'roll_no' => 30,
            'birth_certificate_no' => null,
            'session_year' => now()->year,
            'current_class_id' => $class->id,
            'gender' => 'male',
            'nationality' => 'Bangladeshi',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $profile = StudentProfile::where('roll_no', 30)->whereNull('birth_certificate_no')->firstOrFail();

    expect($profile->user->email)->toBe(sprintf('std%05d@school.com', $profile->id));
});

it('generates a std-prefixed email from the new student_profiles id and forces a password change', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'name' => 'No Address Student',
            'roll_no' => 12,
            'birth_certificate_no' => '2222222222',
            'session_year' => now()->year,
            'current_class_id' => $class->id,
            'gender' => 'male',
            'nationality' => 'Bangladeshi',
            'status' => 'active',
            'present_address' => '',
            'permanent_address' => '',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $profile = StudentProfile::where('birth_certificate_no', '2222222222')->firstOrFail();
    $user = $profile->user;

    expect($user->email)->toBe(sprintf('std%05d@school.com', $profile->id))
        ->and($profile->student_id_no)->toBe(StudentProfile::makeStudentIdNo($profile->id))
        ->and(strlen($profile->student_id_no))->toBe(8)
        ->and($user->must_change_password)->toBeTrue();

    expect(Address::where('addressable_id', $profile->id)->count())->toBe(0);
});

it('flashes the generated credentials and shows them in a modal on the class list page', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'name' => 'Credential Student',
            'roll_no' => 20,
            'birth_certificate_no' => '3333333333',
            'session_year' => now()->year,
            'current_class_id' => $class->id,
            'gender' => 'male',
            'nationality' => 'Bangladeshi',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $profile = StudentProfile::where('birth_certificate_no', '3333333333')->firstOrFail();

    expect(session('generated_student_credentials'))->toBe([
        'student_id' => $profile->student_id_no,
        'password' => 'password',
    ]);
});

it('opens the credentials modal on the create page when using create and create another', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'name' => 'Another Student',
            'roll_no' => 21,
            'birth_certificate_no' => '5555555555',
            'session_year' => now()->year,
            'current_class_id' => $class->id,
            'gender' => 'male',
            'nationality' => 'Bangladeshi',
            'status' => 'active',
        ])
        ->call('createAnother')
        ->assertHasNoFormErrors()
        ->assertActionMounted('studentCredentials')
        ->assertSet('mountedActions.0.arguments.student_id', StudentProfile::where('birth_certificate_no', '5555555555')->value('student_id_no'));
});

it('mounts the credentials modal on the class list page when credentials are flashed', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    $this->withSession([
        'generated_student_credentials' => ['student_id' => '00100099', 'password' => 'password'],
    ]);

    // The action itself is mounted client-side via wire:init once the component
    // has booted (see StudentsByClass::mount()), which Livewire::test() can't
    // execute — so this asserts the properties that drive that wire:init call,
    // then mounts the action manually to prove the modal actually opens with the
    // credentials (it would silently no-op if the action were hidden).
    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->assertSet('defaultAction', 'studentCredentials')
        ->assertSet('defaultActionArguments', ['student_id' => '00100099', 'password' => 'password'])
        ->mountAction('studentCredentials', ['student_id' => '00100099', 'password' => 'password'])
        ->assertActionMounted('studentCredentials')
        ->assertSee('00100099');

    expect(session('generated_student_credentials'))->toBeNull();
});

it('never touches the email when editing an existing student profile and changing roll no', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);
    $user = User::factory()->create(['email' => 'existing.student@example.com']);
    $profile = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 7,
        'birth_certificate_no' => '4444444444',
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    Livewire::test(EditStudentProfile::class, ['record' => $profile->id])
        ->assertFormSet(['student_id_no' => $profile->student_id_no])
        ->assertFormFieldIsDisabled('student_id_no')
        ->fillForm(['roll_no' => 15])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->email)->toBe('existing.student@example.com');
});

it('saves the guardian phone number on create and lets it be updated on edit', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'name' => 'Guardian Phone Student',
            'roll_no' => 40,
            'birth_certificate_no' => '6666666666',
            'session_year' => now()->year,
            'current_class_id' => $class->id,
            'gender' => 'male',
            'nationality' => 'Bangladeshi',
            'status' => 'active',
            'guardian_phone' => '01712345678',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $profile = StudentProfile::where('birth_certificate_no', '6666666666')->firstOrFail();

    expect($profile->guardian_phone)->toBe('01712345678');

    Livewire::test(EditStudentProfile::class, ['record' => $profile->id])
        ->assertFormSet(['guardian_phone' => '01712345678'])
        ->fillForm(['guardian_phone' => '01898765432'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($profile->fresh()->guardian_phone)->toBe('01898765432');
});
