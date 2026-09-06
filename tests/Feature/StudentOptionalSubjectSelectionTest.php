<?php

use App\Enums\OptionalSubjectRole;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\CreateStudentProfile;
use App\Filament\Resources\StudentProfiles\Pages\EditStudentProfile;
use App\Models\Classes;
use App\Models\Group;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * class_group_subject only lists a group's optional subject *pool* now
 * (compulsory/optional) — which subject becomes a student's main_optional vs
 * extra_optional is chosen per student on the student profile form, and
 * persisted to student_optional_subjects.
 */
function makeOptionalSelectionTestGroupClass(): array
{
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'has_group' => true, 'is_active' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $class->groups()->attach($group->id);

    $biology = Subject::create(['name' => 'Biology', 'has_written' => true, 'is_active' => true]);
    $higherMath = Subject::create(['name' => 'Higher Math', 'has_written' => true, 'is_active' => true]);

    $biology->classes()->attach($class->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);
    $higherMath->classes()->attach($class->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);

    return [$class, $group, $biology, $higherMath];
}

it('saves the student-chosen main and extra optional subjects on create', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    [$class, $group, $biology, $higherMath] = makeOptionalSelectionTestGroupClass();

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'name' => 'Optional Subject Student',
            'roll_no' => 11,
            'session_year' => now()->year,
            'current_class_id' => $class->id,
            'current_group_id' => $group->id,
            'main_optional_subject_id' => $biology->id,
            'extra_optional_subject_id' => $higherMath->id,
            'gender' => 'male',
            'nationality' => 'Bangladeshi',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $profile = StudentProfile::where('roll_no', 11)->firstOrFail();

    expect(StudentOptionalSubject::where('student_id', $profile->id)
        ->where('class_id', $class->id)
        ->where('role', OptionalSubjectRole::MainOptional)
        ->value('subject_id'))->toBe($biology->id);

    expect(StudentOptionalSubject::where('student_id', $profile->id)
        ->where('class_id', $class->id)
        ->where('role', OptionalSubjectRole::ExtraOptional)
        ->value('subject_id'))->toBe($higherMath->id);
});

it('rejects picking the same subject for both main and extra optional', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    [$class, $group, $biology] = makeOptionalSelectionTestGroupClass();

    Livewire::test(CreateStudentProfile::class)
        ->fillForm([
            'name' => 'Duplicate Optional Student',
            'roll_no' => 12,
            'session_year' => now()->year,
            'current_class_id' => $class->id,
            'current_group_id' => $group->id,
            'main_optional_subject_id' => $biology->id,
            'extra_optional_subject_id' => $biology->id,
            'gender' => 'male',
            'nationality' => 'Bangladeshi',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasFormErrors(['extra_optional_subject_id']);
});

it('prefills main/extra optional subjects when editing, and updates the choice on save', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    [$class, $group, $biology, $higherMath] = makeOptionalSelectionTestGroupClass();

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $profile = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 13,
        'current_class_id' => $class->id,
        'current_group_id' => $group->id,
        'session_year' => now()->year,
        'gender' => 'male',
        'status' => 'active',
    ]);

    StudentOptionalSubject::create([
        'student_id' => $profile->id,
        'class_id' => $class->id,
        'group_id' => $group->id,
        'subject_id' => $biology->id,
        'role' => OptionalSubjectRole::MainOptional,
    ]);

    Livewire::test(EditStudentProfile::class, ['record' => $profile->id])
        ->assertFormSet(['main_optional_subject_id' => $biology->id])
        ->fillForm([
            'main_optional_subject_id' => $higherMath->id,
            'extra_optional_subject_id' => $biology->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(StudentOptionalSubject::where('student_id', $profile->id)
        ->where('role', OptionalSubjectRole::MainOptional)
        ->value('subject_id'))->toBe($higherMath->id);

    expect(StudentOptionalSubject::where('student_id', $profile->id)
        ->where('role', OptionalSubjectRole::ExtraOptional)
        ->value('subject_id'))->toBe($biology->id);
});
