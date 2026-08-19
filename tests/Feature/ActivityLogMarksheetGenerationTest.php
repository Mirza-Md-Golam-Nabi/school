<?php

use App\Actions\GenerateMarksheetsForExamAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function createMarksheetLogTestStudent(int $classId, StudentStatus $status = StudentStatus::Active): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 100000),
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

function createMarksheetLogTestExam(int $classId): Exam
{
    $examType = ExamType::create(['name' => 'Annual', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classId,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);
}

function createMarksheetLogTestRanking(Exam $exam, int $classId, StudentProfile $student): StudentMeritRanking
{
    return StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $classId,
        'total_marks' => 80,
        'gpa' => 4.0,
    ]);
}

it('logs a single summary entry with a readable exam label when marksheets are generated', function () {
    Queue::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $exam = createMarksheetLogTestExam($class->id);

    $ranked = createMarksheetLogTestStudent($class->id);
    $unranked = createMarksheetLogTestStudent($class->id);
    createMarksheetLogTestRanking($exam, $class->id, $ranked);

    app(GenerateMarksheetsForExamAction::class)->handle($exam, $admin->id);

    $activities = Activity::where('log_name', 'marksheet_generation')->where('event', 'generated')->get();

    expect($activities)->toHaveCount(1);

    $activity = $activities->first();
    $examLabel = $exam->displayLabel();

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($exam->id)
        ->and($activity->description)->toBe(
            "Generated 1 marksheet(s) for \"{$examLabel}\" (0 regenerated) — 1 skipped (no ranking yet)."
        );

    expect($activity->properties->get('attributes'))
        ->toMatchArray([
            'exam_id' => $exam->id,
            'exam_id_label' => $examLabel,
            'created_count' => 1,
            'regenerated_count' => 0,
            'skipped_no_ranking_count' => 1,
        ]);
});

it('reflects regenerated counts in the description when marksheets are re-run for the same exam', function () {
    Queue::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Two', 'order' => 2]);
    $exam = createMarksheetLogTestExam($class->id);
    $student = createMarksheetLogTestStudent($class->id);
    createMarksheetLogTestRanking($exam, $class->id, $student);

    app(GenerateMarksheetsForExamAction::class)->handle($exam, $admin->id);
    app(GenerateMarksheetsForExamAction::class)->handle($exam, $admin->id);

    $activities = Activity::where('log_name', 'marksheet_generation')->where('event', 'generated')->get();

    expect($activities)->toHaveCount(2);

    $secondActivity = $activities->last();

    expect($secondActivity->description)->toBe(
        'Generated 0 marksheet(s) for "'.$exam->displayLabel().'" (1 regenerated) — 0 skipped (no ranking yet).'
    );
});
