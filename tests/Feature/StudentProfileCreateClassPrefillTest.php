<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\CreateStudentProfile;
use App\Filament\Resources\StudentProfiles\Pages\EditStudentProfile;
use App\Models\Address;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('prefills the class field when classId is passed as a query parameter', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    $response = $this->actingAs($admin)->get("/admin/student-profiles/create?classId={$class->id}");

    $response->assertOk();
    $response->assertSee('&quot;current_class_id&quot;:'.$class->id, false);
});

it('leaves the class field empty when no classId is passed', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    $response = $this->actingAs($admin)->get('/admin/student-profiles/create');

    $response->assertOk();
    $response->assertDontSee('&quot;current_class_id&quot;:'.$class->id, false);
});

it('defaults the password field to "password" on create', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    Livewire::test(CreateStudentProfile::class)
        ->assertFormSet(['password' => 'password']);
});

it('sets the email field value only after class and roll no are both filled', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->assertFormSet(['email' => null])
        ->fillForm(['current_class_id' => $class->id])
        ->assertFormSet(['email' => null])
        ->fillForm(['roll_no' => 7])
        ->assertFormSet(['email' => 'class_05_07@example.com']);
});

it('regenerates the auto-filled email when roll no is changed afterwards', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm(['current_class_id' => $class->id, 'roll_no' => 7])
        ->assertFormSet(['email' => 'class_05_07@example.com'])
        ->fillForm(['roll_no' => 15])
        ->assertFormSet(['email' => 'class_05_15@example.com']);
});

it('pads a 1-digit roll no with a leading zero but leaves 2-3 digit roll numbers untouched', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm(['current_class_id' => $class->id, 'roll_no' => 5])
        ->assertFormSet(['email' => 'class_05_05@example.com'])
        ->fillForm(['roll_no' => 57])
        ->assertFormSet(['email' => 'class_05_57@example.com'])
        ->fillForm(['roll_no' => 123])
        ->assertFormSet(['email' => 'class_05_123@example.com']);
});

it('stops regenerating the email once the user edits it manually, even after roll no changes again', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm(['current_class_id' => $class->id, 'roll_no' => 7])
        ->assertFormSet(['email' => 'class_05_07@example.com'])
        ->fillForm(['email' => 'custom@example.com'])
        ->fillForm(['roll_no' => 15])
        ->assertFormSet(['email' => 'custom@example.com']);
});

it('never touches the email when editing an existing student profile and changing roll no', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);
    $user = User::factory()->create(['email' => 'existing.student@example.com']);
    $profile = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 7,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    Livewire::test(EditStudentProfile::class, ['record' => $profile->id])
        ->assertFormSet(['email' => 'existing.student@example.com'])
        ->fillForm(['roll_no' => 15])
        ->assertFormSet(['email' => 'existing.student@example.com']);
});

it('does not overwrite an email the user already typed', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm(['email' => 'custom@example.com'])
        ->fillForm(['current_class_id' => $class->id, 'roll_no' => 7])
        ->assertFormSet(['email' => 'custom@example.com']);
});

it('appends the next user id when the generated email already exists', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);
    User::factory()->create(['email' => 'class_05_07@example.com']);

    $nextId = User::max('id') + 1;

    Livewire::test(CreateStudentProfile::class)
        ->fillForm(['current_class_id' => $class->id, 'roll_no' => 7])
        ->assertFormSet(['email' => "class_05_07_{$nextId}@example.com"]);
});

it('creates a student profile without any address rows when both address fields are left blank', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'email' => 'noaddress@example.com',
            'name' => 'No Address Student',
            'password' => 'password',
            'roll_no' => 12,
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

    $user = User::where('email', 'noaddress@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->studentProfile)->not->toBeNull();

    expect(Address::where('addressable_id', $user->studentProfile->id)->count())->toBe(0);
});
