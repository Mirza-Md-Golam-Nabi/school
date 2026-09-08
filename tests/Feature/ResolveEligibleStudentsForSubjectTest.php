<?php

use App\Actions\ResolveEligibleStudentsForSubject;
use App\Enums\Gender;
use App\Enums\OptionalSubjectRole;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Group;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeEligibilityStudent(Classes $class, ?Group $group = null, int $rollNo = 1): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'current_group_id' => $group?->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('returns no students when the class has no attachment for the subject', function () {
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $subject = Subject::create(['name' => 'Physics', 'has_mcq' => true, 'has_written' => true, 'has_practical' => true, 'is_active' => true]);
    makeEligibilityStudent($class);

    $students = app(ResolveEligibleStudentsForSubject::class)->execute($class->id, $subject->id);

    expect($students)->toHaveCount(0);
});

it('includes every student for a compulsory subject attached with no group restriction', function () {
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $science = Group::create(['name' => 'Science', 'is_active' => true]);
    $commerce = Group::create(['name' => 'Commerce', 'is_active' => true]);

    $subject = Subject::create(['name' => 'Bangla 1st Paper', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['group_id' => null, 'subject_type' => SubjectType::Compulsory->value]);

    $scienceStudent = makeEligibilityStudent($class, $science, 1);
    $commerceStudent = makeEligibilityStudent($class, $commerce, 2);
    $noGroupStudent = makeEligibilityStudent($class, null, 3);

    $students = app(ResolveEligibleStudentsForSubject::class)->execute($class->id, $subject->id);

    expect($students->pluck('id')->sort()->values()->all())
        ->toBe(collect([$scienceStudent->id, $commerceStudent->id, $noGroupStudent->id])->sort()->values()->all());
});

it('only includes the matching group for a group-specific compulsory subject', function () {
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $science = Group::create(['name' => 'Science', 'is_active' => true]);
    $commerce = Group::create(['name' => 'Commerce', 'is_active' => true]);

    $subject = Subject::create(['name' => 'Physics', 'has_mcq' => true, 'has_written' => true, 'has_practical' => true, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['group_id' => $science->id, 'subject_type' => SubjectType::Compulsory->value]);

    $scienceStudent = makeEligibilityStudent($class, $science, 1);
    makeEligibilityStudent($class, $commerce, 2);

    $students = app(ResolveEligibleStudentsForSubject::class)->execute($class->id, $subject->id);

    expect($students->pluck('id')->all())->toBe([$scienceStudent->id]);
});

it('only includes students who picked a group-specific optional subject', function () {
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $science = Group::create(['name' => 'Science', 'is_active' => true]);

    $subject = Subject::create(['name' => 'Biology', 'has_mcq' => true, 'has_written' => true, 'has_practical' => true, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['group_id' => $science->id, 'subject_type' => SubjectType::Optional->value]);

    $studentWhoChoseIt = makeEligibilityStudent($class, $science, 1);
    $studentWhoDidNot = makeEligibilityStudent($class, $science, 2);

    StudentOptionalSubject::create([
        'student_id' => $studentWhoChoseIt->id,
        'class_id' => $class->id,
        'group_id' => $science->id,
        'subject_id' => $subject->id,
        'role' => OptionalSubjectRole::MainOptional,
    ]);

    $students = app(ResolveEligibleStudentsForSubject::class)->execute($class->id, $subject->id);

    expect($students->pluck('id')->all())->toBe([$studentWhoChoseIt->id]);
});

it('only includes students who picked an all-groups optional subject, regardless of their own group', function () {
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $science = Group::create(['name' => 'Science', 'is_active' => true]);
    $commerce = Group::create(['name' => 'Commerce', 'is_active' => true]);

    $subject = Subject::create(['name' => 'Economics', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['group_id' => null, 'subject_type' => SubjectType::Optional->value]);

    $commerceStudentWhoChoseIt = makeEligibilityStudent($class, $commerce, 1);
    makeEligibilityStudent($class, $science, 2);

    StudentOptionalSubject::create([
        'student_id' => $commerceStudentWhoChoseIt->id,
        'class_id' => $class->id,
        'group_id' => $commerce->id,
        'subject_id' => $subject->id,
        'role' => OptionalSubjectRole::ExtraOptional,
    ]);

    $students = app(ResolveEligibleStudentsForSubject::class)->execute($class->id, $subject->id);

    expect($students->pluck('id')->all())->toBe([$commerceStudentWhoChoseIt->id]);
});

it('prefers the group-specific attachment over the all-groups one for the same subject', function () {
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $commerce = Group::create(['name' => 'Commerce', 'is_active' => true]);
    $arts = Group::create(['name' => 'Arts', 'is_active' => true]);

    $subject = Subject::create(['name' => 'General Science', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['group_id' => $commerce->id, 'subject_type' => SubjectType::Compulsory->value]);
    $subject->classes()->attach($class->id, ['group_id' => $arts->id, 'subject_type' => SubjectType::Compulsory->value]);

    $commerceStudent = makeEligibilityStudent($class, $commerce, 1);
    $artsStudent = makeEligibilityStudent($class, $arts, 2);

    $students = app(ResolveEligibleStudentsForSubject::class)->execute($class->id, $subject->id);

    expect($students->pluck('id')->sort()->values()->all())
        ->toBe(collect([$commerceStudent->id, $artsStudent->id])->sort()->values()->all());
});
