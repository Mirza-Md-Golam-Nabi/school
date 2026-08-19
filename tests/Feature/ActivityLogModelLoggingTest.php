<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\ExamType;
use App\Models\Group;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('records the acting user as causer when a logged model is created', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => 1,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $activity = Activity::where('log_name', 'student_profile')
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($student->id);
});

it('logs only the changed attributes on update', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => 1,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $student->update(['roll_no' => 2]);

    $activity = Activity::where('log_name', 'student_profile')
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('attributes'))->toHaveKey('roll_no');
    expect($activity->properties->get('attributes'))->not->toHaveKey('gender');
});

it('logs deletion of a tracked model', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => 1,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $student->delete();

    $activity = Activity::where('log_name', 'student_profile')
        ->where('event', 'deleted')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id);
});

it('logs create, update, and delete of a subject', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin);

    $subject = Subject::create([
        'name' => 'Mathematics',
        'code' => 'MATH101',
        'has_mcq' => true,
        'has_written' => true,
        'has_practical' => false,
        'is_active' => true,
    ]);

    $createdActivity = Activity::where('log_name', 'subject')
        ->where('event', 'created')
        ->first();

    expect($createdActivity)->not->toBeNull()
        ->and($createdActivity->causer_id)->toBe($admin->id)
        ->and($createdActivity->subject_id)->toBe($subject->id)
        ->and($createdActivity->description)->toBe('Created subject "Mathematics".');

    $subject->update(['name' => 'Higher Mathematics']);

    $updatedActivity = Activity::where('log_name', 'subject')
        ->where('event', 'updated')
        ->first();

    expect($updatedActivity)->not->toBeNull();
    expect($updatedActivity->properties->get('attributes'))->toHaveKey('name');
    expect($updatedActivity->properties->get('attributes'))->not->toHaveKey('code');
    expect($updatedActivity->description)->toBe('Updated subject "Higher Mathematics".');

    $subject->delete();

    $deletedActivity = Activity::where('log_name', 'subject')
        ->where('event', 'deleted')
        ->first();

    expect($deletedActivity)->not->toBeNull()
        ->and($deletedActivity->causer_id)->toBe($admin->id)
        ->and($deletedActivity->description)->toBe('Deleted subject "Higher Mathematics".');
});

it('logs create, update, and delete of a group', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin);

    $group = Group::create([
        'name' => 'Science',
        'is_active' => true,
    ]);

    $createdActivity = Activity::where('log_name', 'group')
        ->where('event', 'created')
        ->first();

    expect($createdActivity)->not->toBeNull()
        ->and($createdActivity->causer_id)->toBe($admin->id)
        ->and($createdActivity->subject_id)->toBe($group->id)
        ->and($createdActivity->description)->toBe('Created group "Science".');

    $group->update(['name' => 'Commerce']);

    $updatedActivity = Activity::where('log_name', 'group')
        ->where('event', 'updated')
        ->first();

    expect($updatedActivity)->not->toBeNull();
    expect($updatedActivity->properties->get('attributes'))->toHaveKey('name');
    expect($updatedActivity->description)->toBe('Updated group "Commerce".');

    $group->delete();

    $deletedActivity = Activity::where('log_name', 'group')
        ->where('event', 'deleted')
        ->first();

    expect($deletedActivity)->not->toBeNull()
        ->and($deletedActivity->causer_id)->toBe($admin->id)
        ->and($deletedActivity->description)->toBe('Deleted group "Commerce".');
});

it('logs create, update, and delete of a class', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin);

    $class = Classes::create([
        'name' => 'Class 6',
        'order' => 6,
    ]);

    $createdActivity = Activity::where('log_name', 'class')
        ->where('event', 'created')
        ->first();

    expect($createdActivity)->not->toBeNull()
        ->and($createdActivity->causer_id)->toBe($admin->id)
        ->and($createdActivity->subject_id)->toBe($class->id)
        ->and($createdActivity->description)->toBe('Created class "Class 6".');

    $class->update(['name' => 'Class Six']);

    $updatedActivity = Activity::where('log_name', 'class')
        ->where('event', 'updated')
        ->first();

    expect($updatedActivity)->not->toBeNull();
    expect($updatedActivity->properties->get('attributes'))->toHaveKey('name');
    expect($updatedActivity->description)->toBe('Updated class "Class Six".');

    $class->delete();

    $deletedActivity = Activity::where('log_name', 'class')
        ->where('event', 'deleted')
        ->first();

    expect($deletedActivity)->not->toBeNull()
        ->and($deletedActivity->causer_id)->toBe($admin->id)
        ->and($deletedActivity->description)->toBe('Deleted class "Class Six".');
});

it('logs create, update, and delete of a section with its class for context', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 9', 'order' => 9]);

    $section = Section::create(['class_id' => $class->id, 'name' => 'Section A']);

    $createdActivity = Activity::where('log_name', 'section')
        ->where('event', 'created')
        ->first();

    expect($createdActivity)->not->toBeNull()
        ->and($createdActivity->causer_id)->toBe($admin->id)
        ->and($createdActivity->description)->toBe('Created section "Section A" (Class 9).');

    $section->update(['name' => 'Section B']);

    $updatedActivity = Activity::where('log_name', 'section')
        ->where('event', 'updated')
        ->first();

    expect($updatedActivity)->not->toBeNull()
        ->and($updatedActivity->description)->toBe('Updated section "Section B" (Class 9).');

    $section->delete();

    $deletedActivity = Activity::where('log_name', 'section')
        ->where('event', 'deleted')
        ->first();

    expect($deletedActivity)->not->toBeNull()
        ->and($deletedActivity->causer_id)->toBe($admin->id)
        ->and($deletedActivity->description)->toBe('Deleted section "Section B" (Class 9).');
});

it('resolves class_teacher_id to the teacher name alongside the raw id', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin);

    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => 'Karim Sir', 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $class = Classes::create([
        'name' => 'Class 7',
        'order' => 7,
        'class_teacher_id' => $teacher->id,
    ]);

    $createdActivity = Activity::where('log_name', 'class')
        ->where('event', 'created')
        ->first();

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'class_teacher_id' => $teacher->id,
            'class_teacher_id_label' => 'Karim Sir',
        ]);
});

it('logs create, update, and delete of an exam type', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $this->actingAs($admin);

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $createdActivity = Activity::where('log_name', 'exam_type')
        ->where('event', 'created')
        ->first();

    expect($createdActivity)->not->toBeNull()
        ->and($createdActivity->causer_id)->toBe($admin->id)
        ->and($createdActivity->subject_id)->toBe($examType->id)
        ->and($createdActivity->description)->toBe('Created exam type "Half Yearly".');

    $examType->update(['name' => 'Half Yearly Exam']);

    $updatedActivity = Activity::where('log_name', 'exam_type')
        ->where('event', 'updated')
        ->first();

    expect($updatedActivity)->not->toBeNull();
    expect($updatedActivity->properties->get('attributes'))->toHaveKey('name');
    expect($updatedActivity->description)->toBe('Updated exam type "Half Yearly Exam".');

    $examType->delete();

    $deletedActivity = Activity::where('log_name', 'exam_type')
        ->where('event', 'deleted')
        ->first();

    expect($deletedActivity)->not->toBeNull()
        ->and($deletedActivity->causer_id)->toBe($admin->id)
        ->and($deletedActivity->description)->toBe('Deleted exam type "Half Yearly Exam".');
});
