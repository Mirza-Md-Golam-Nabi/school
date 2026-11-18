<?php

use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\CreateStudentProfile;
use App\Filament\Resources\StudentProfiles\Pages\EditStudentProfile;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
    $this->class = Classes::create(['name' => 'Class 5', 'order' => 5]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function banglaNameTestFormData(int $classId, array $overrides = []): array
{
    return [
        'roll_no' => 1,
        'session_year' => now()->year,
        'current_class_id' => $classId,
        'gender' => 'male',
        'nationality' => 'Bangladeshi',
        'status' => 'active',
        ...$overrides,
    ];
}

it('requires the Bangla name when creating a student', function () {
    Livewire::test(CreateStudentProfile::class)
        ->fillForm(banglaNameTestFormData($this->class->id, ['name' => 'Abdur Rahman']))
        ->call('create')
        ->assertHasFormErrors(['name_bn' => 'required']);

    expect(StudentProfile::count())->toBe(0);
});

it('saves both names when the English name is given', function () {
    Livewire::test(CreateStudentProfile::class)
        ->fillForm(banglaNameTestFormData($this->class->id, ['name_bn' => 'আব্দুর রহমান', 'name' => 'Abdur Rahman']))
        ->call('create')
        ->assertHasNoFormErrors();

    $profile = StudentProfile::sole();

    expect($profile->name_bn)->toBe('আব্দুর রহমান')
        ->and($profile->user->name)->toBe('Abdur Rahman');
});

it('uses the Bangla name as the display name when the English name is left blank', function () {
    Livewire::test(CreateStudentProfile::class)
        ->fillForm(banglaNameTestFormData($this->class->id, ['name_bn' => 'আব্দুর রহমান', 'name' => null]))
        ->call('create')
        ->assertHasNoFormErrors();

    $profile = StudentProfile::sole();

    expect($profile->name_bn)->toBe('আব্দুর রহমান')
        ->and($profile->user->name)->toBe('আব্দুর রহমান');
});

it('shows a blank English name on edit when only the Bangla name was saved, and updates both', function () {
    Livewire::test(CreateStudentProfile::class)
        ->fillForm(banglaNameTestFormData($this->class->id, ['name_bn' => 'আব্দুর রহমান']))
        ->call('create');

    $profile = StudentProfile::sole();

    Livewire::test(EditStudentProfile::class, ['record' => $profile->id])
        ->assertSchemaStateSet(['name_bn' => 'আব্দুর রহমান', 'name' => null])
        ->fillForm(['name_bn' => 'ফাতেমা আক্তার', 'name' => 'Fatema Akter'])
        ->call('save')
        ->assertHasNoFormErrors();

    $profile->refresh();

    expect($profile->name_bn)->toBe('ফাতেমা আক্তার')
        ->and($profile->user->name)->toBe('Fatema Akter');
});
