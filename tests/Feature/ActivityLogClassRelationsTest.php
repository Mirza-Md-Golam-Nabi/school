<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function actingAsActivityLogAdmin(): User
{
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin);

    return $admin;
}

it('logs create, update, and delete of a section', function () {
    $admin = actingAsActivityLogAdmin();

    $class = Classes::create(['name' => 'Class 8', 'order' => 8, 'has_section' => true]);

    $section = Section::create(['class_id' => $class->id, 'name' => 'Section A']);

    $createdActivity = Activity::where('log_name', 'section')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id);

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'class_id' => $class->id,
            'class_id_label' => 'Class 8',
        ]);

    $section->update(['name' => 'Section B']);

    expect(Activity::where('log_name', 'section')->where('event', 'updated')->first())
        ->not->toBeNull();

    $section->delete();

    expect(Activity::where('log_name', 'section')->where('event', 'deleted')->first())
        ->not->toBeNull();
});

it('logs create and delete of a teacher-subject assignment', function () {
    $admin = actingAsActivityLogAdmin();

    $class = Classes::create(['name' => 'Class 9', 'order' => 9]);

    $subject = Subject::create([
        'name' => 'Physics',
        'has_mcq' => true,
        'has_written' => true,
        'has_practical' => true,
        'is_active' => true,
    ]);

    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => 'Rahim Sir', 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $assignment = TeacherSubject::firstOrCreate([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'section_id' => null,
        'session_year' => now()->year,
    ]);

    $createdActivity = Activity::where('log_name', 'teacher_subject')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Assigned teacher "Rahim Sir" to teach "Physics" in class "Class 9" (No Section).');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'teacher_id' => $teacher->id,
            'teacher_id_label' => 'Rahim Sir',
            'subject_id' => $subject->id,
            'subject_id_label' => 'Physics',
            'class_id' => $class->id,
            'class_id_label' => 'Class 9',
            'section_id' => null,
            'section_id_label' => 'No Section',
        ]);

    $assignment->delete();

    expect(Activity::where('log_name', 'teacher_subject')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Unassigned teacher "Rahim Sir" from "Physics" in class "Class 9" (No Section).');
});

it('logs attach, pivot update, and detach of a subject on a class', function () {
    $admin = actingAsActivityLogAdmin();

    $class = Classes::create(['name' => 'Class 10', 'order' => 10]);

    $subject = Subject::create([
        'name' => 'Chemistry',
        'has_mcq' => true,
        'has_written' => true,
        'has_practical' => true,
        'is_active' => true,
    ]);

    $class->subjects()->attach($subject->id, ['subject_type' => SubjectType::Compulsory]);

    $createdActivity = Activity::where('log_name', 'class_subject')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Attached subject "Chemistry" to class "Class 10" (All Groups).');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'class_id' => $class->id,
            'class_id_label' => 'Class 10',
            'group_id' => null,
            'group_id_label' => 'All Groups',
            'subject_id' => $subject->id,
            'subject_id_label' => 'Chemistry',
        ]);

    $class->subjects()->updateExistingPivot($subject->id, ['subject_type' => SubjectType::MainOptional]);

    expect(Activity::where('log_name', 'class_subject')->where('event', 'updated')->first())
        ->not->toBeNull()
        ->description->toBe('Updated subject "Chemistry" attachment on class "Class 10" (All Groups).');

    $class->subjects()->detach($subject->id);

    expect(Activity::where('log_name', 'class_subject')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Detached subject "Chemistry" from class "Class 10" (All Groups).');
});

it('logs attach and detach of a group on a class', function () {
    $admin = actingAsActivityLogAdmin();

    $class = Classes::create(['name' => 'Class 11', 'order' => 11, 'has_group' => true]);

    $group = Group::create(['name' => 'Science', 'is_active' => true]);

    $class->groups()->attach($group->id);

    $createdActivity = Activity::where('log_name', 'class_group')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Attached group "Science" to class "Class 11".');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'class_id' => $class->id,
            'class_id_label' => 'Class 11',
            'group_id' => $group->id,
            'group_id_label' => 'Science',
        ]);

    $class->groups()->detach($group->id);

    expect(Activity::where('log_name', 'class_group')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Detached group "Science" from class "Class 11".');
});
