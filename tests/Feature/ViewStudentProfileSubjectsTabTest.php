<?php

use App\Enums\Gender;
use App\Enums\OptionalSubjectRole;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Group;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows compulsory, group, and additional subjects in three separate sections on the student view page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'has_group' => true]);
    $science = Group::create(['name' => 'Science']);
    $class->groups()->attach($science->id);

    $bangla = Subject::create(['name' => 'Bangla 1st Paper', 'has_mcq' => false]);
    $physics = Subject::create(['name' => 'Physics', 'has_mcq' => true]);
    $biology = Subject::create(['name' => 'Biology', 'has_mcq' => true]);
    $economics = Subject::create(['name' => 'Economics', 'has_mcq' => true]);

    ClassGroupSubject::create(['class_id' => $class->id, 'group_id' => null, 'subject_id' => $bangla->id, 'subject_type' => SubjectType::Compulsory]);
    ClassGroupSubject::create(['class_id' => $class->id, 'group_id' => $science->id, 'subject_id' => $physics->id, 'subject_type' => SubjectType::Compulsory]);
    ClassGroupSubject::create(['class_id' => $class->id, 'group_id' => $science->id, 'subject_id' => $biology->id, 'subject_type' => SubjectType::Optional]);
    ClassGroupSubject::create(['class_id' => $class->id, 'group_id' => null, 'subject_id' => $economics->id, 'subject_type' => SubjectType::Optional]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'current_group_id' => $science->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'group_id' => $science->id,
        'subject_id' => $biology->id,
        'role' => OptionalSubjectRole::MainOptional,
    ]);

    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'group_id' => $science->id,
        'subject_id' => $economics->id,
        'role' => OptionalSubjectRole::ExtraOptional,
    ]);

    $response = $this->actingAs($admin)->get(StudentProfileResource::getUrl('view', ['record' => $student]));

    // Physics is compulsory but Science-group-specific, so it belongs under
    // "Group Subject" alongside the chosen main-optional (Biology) — not
    // under "Compulsory Subjects", which is only the all-groups list.
    $response->assertOk()
        ->assertSee('Subjects')
        ->assertSeeInOrder([
            'Compulsory Subjects',
            'Bangla 1st Paper',
            'Group Subject',
            'Biology',
            'Physics',
            'Additional Subject',
            'Economics',
        ]);
});

it('hides the group and additional subject headings entirely when a student has neither', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $class = Classes::create(['name' => 'Class 7', 'order' => 7, 'has_group' => false]);
    $bangla = Subject::create(['name' => 'Bangla 1st Paper', 'has_mcq' => false]);

    ClassGroupSubject::create(['class_id' => $class->id, 'group_id' => null, 'subject_id' => $bangla->id, 'subject_type' => SubjectType::Compulsory]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $response = $this->actingAs($admin)->get(StudentProfileResource::getUrl('view', ['record' => $student]));

    $response->assertOk()
        ->assertSee('Compulsory Subjects')
        ->assertSee('Bangla 1st Paper')
        ->assertDontSee('Group Subject')
        ->assertDontSee('Additional Subject');
});
