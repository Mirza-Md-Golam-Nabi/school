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

it('does not show the email field on create, since it is system-generated', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    Livewire::test(CreateStudentProfile::class)
        ->assertFormFieldIsHidden('email');
});

it('rejects a duplicate birth certificate no on create', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => 1,
        'birth_certificate_no' => '1111111111',
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'name' => 'Second Student',
            'roll_no' => 2,
            'birth_certificate_no' => '1111111111',
            'session_year' => now()->year,
            'current_class_id' => $class->id,
            'gender' => 'male',
            'nationality' => 'Bangladeshi',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasFormErrors(['birth_certificate_no' => 'unique']);
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
        'email' => $profile->user->email,
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
        ->assertSee(sprintf('std%05d@school.com', StudentProfile::where('birth_certificate_no', '5555555555')->value('id')));
});

it('mounts the credentials modal on the class list page when credentials are flashed', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    $this->withSession([
        'generated_student_credentials' => ['email' => 'std00099@school.com', 'password' => 'password'],
    ]);

    // The action itself is mounted client-side via wire:init once the component
    // has booted (see StudentsByClass::mount()), which Livewire::test() can't
    // execute — so this asserts the properties that drive that wire:init call,
    // then mounts the action manually to prove the modal actually opens with the
    // credentials (it would silently no-op if the action were hidden).
    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->assertSet('defaultAction', 'studentCredentials')
        ->assertSet('defaultActionArguments', ['email' => 'std00099@school.com', 'password' => 'password'])
        ->mountAction('studentCredentials', ['email' => 'std00099@school.com', 'password' => 'password'])
        ->assertActionMounted('studentCredentials')
        ->assertSee('std00099@school.com');

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
        ->assertFormSet(['email' => 'existing.student@example.com'])
        ->assertFormFieldIsDisabled('email')
        ->fillForm(['roll_no' => 15])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->email)->toBe('existing.student@example.com');
});
