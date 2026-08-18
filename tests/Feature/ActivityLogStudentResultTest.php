<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs create, update, and delete of a student result with relation labels and a readable description', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 8', 'order' => 8]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => 2026,
        'start_date' => '2026-08-22',
        'end_date' => '2026-08-26',
        'is_published' => false,
    ]);

    $subject = Subject::create([
        'name' => 'Physics',
        'has_mcq' => false,
        'has_written' => true,
        'has_practical' => false,
        'is_active' => true,
    ]);

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true, 'name' => 'Karim Hossain']);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $result = StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 24,
        'is_absent' => false,
    ]);

    $createdActivity = Activity::where('log_name', 'student_result')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Class 8 (2026) - Half Yearly - Physics - Karim Hossain (Roll: 5)');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'exam_id' => $exam->id,
            'exam_id_label' => 'Half Yearly - Class 8 (2026)',
            'class_id' => $class->id,
            'class_id_label' => 'Class 8',
            'subject_id' => $subject->id,
            'subject_id_label' => 'Physics',
            'student_id' => $student->id,
            'student_id_label' => 'Karim Hossain (Roll: 5)',
        ]);

    $result->update(['written_marks' => 20]);

    $updatedActivity = Activity::where('log_name', 'student_result')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Class 8 (2026) - Half Yearly - Physics - Karim Hossain (Roll: 5)');

    $result->delete();

    expect(Activity::where('log_name', 'student_result')->where('event', 'deleted')->first())
        ->not->toBeNull();
});
