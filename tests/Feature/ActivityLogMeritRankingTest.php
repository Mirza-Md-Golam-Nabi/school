<?php

use App\Actions\CalculateExamRankings;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs a single summary entry — with exam and class context — when merit rankings are calculated', function () {
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
        'is_published' => true,
    ]);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    foreach ([1, 2] as $rollNo) {
        $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
        $student = StudentProfile::create([
            'user_id' => $user->id,
            'roll_no' => $rollNo,
            'current_class_id' => $class->id,
            'session_year' => 2026,
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        StudentResult::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'written_marks' => 70,
        ]);
    }

    app(CalculateExamRankings::class)->execute($exam);

    $activities = Activity::where('log_name', 'merit_ranking')->where('event', 'calculated')->get();

    expect($activities)->toHaveCount(1);

    $activity = $activities->first();

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->description)->toBe('Merit ranking calculated for Half Yearly - Class 8 (2026) — 2 students ranked.')
        ->and($activity->properties->get('attributes'))
        ->toMatchArray([
            'exam_id' => $exam->id,
            'exam_id_label' => 'Half Yearly - Class 8 (2026)',
            'class_id' => $class->id,
            'class_id_label' => 'Class 8',
            'session_year' => 2026,
            'ranked_student_count' => 2,
        ]);
});
