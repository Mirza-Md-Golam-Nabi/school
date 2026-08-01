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
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRankTierTestClass(): Classes
{
    return Classes::create(['name' => 'Class 10', 'order' => 10, 'is_active' => true]);
}

function makeRankTierTestSubject(Classes $class, string $name): Subject
{
    $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    return $subject;
}

function makeRankTierTestStudent(Classes $class): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 999),
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makeRankTierTestExam(Classes $class): Exam
{
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);
}

it('ranks every passing student above every failing student, regardless of raw total marks', function () {
    $class = makeRankTierTestClass();
    $exam = makeRankTierTestExam($class);

    $subjectOne = makeRankTierTestSubject($class, 'Bangla');
    $subjectTwo = makeRankTierTestSubject($class, 'English');

    foreach ([$subjectOne, $subjectTwo] as $subject) {
        ExamSubjectConfig::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'written_total' => 100,
            'total_marks' => 100,
            'pass_mark' => 33,
        ]);
    }

    // High-scoring failer: 90 + 10 = 100 total, but 10/100 = 10% fails Bangla (< 33%).
    $highFailer = makeRankTierTestStudent($class);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subjectOne->id, 'class_id' => $class->id, 'student_id' => $highFailer->id, 'written_marks' => 90]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subjectTwo->id, 'class_id' => $class->id, 'student_id' => $highFailer->id, 'written_marks' => 10]);

    // Modest passer: 40 + 40 = 80 total, both subjects clear the 33% pass mark.
    $modestPasser = makeRankTierTestStudent($class);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subjectOne->id, 'class_id' => $class->id, 'student_id' => $modestPasser->id, 'written_marks' => 40]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subjectTwo->id, 'class_id' => $class->id, 'student_id' => $modestPasser->id, 'written_marks' => 40]);

    // Low-scoring failer: 60 + 10 = 70 total, also fails Bangla — ranks after the high failer within the fail tier.
    $lowFailer = makeRankTierTestStudent($class);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subjectOne->id, 'class_id' => $class->id, 'student_id' => $lowFailer->id, 'written_marks' => 60]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subjectTwo->id, 'class_id' => $class->id, 'student_id' => $lowFailer->id, 'written_marks' => 10]);

    app(CalculateExamRankings::class)->execute($exam);

    $passerRanking = StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $modestPasser->id)->first();
    $highFailerRanking = StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $highFailer->id)->first();
    $lowFailerRanking = StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $lowFailer->id)->first();

    expect($passerRanking->total_marks)->toBe(80.0)
        ->and($highFailerRanking->total_marks)->toBe(100.0)
        ->and($lowFailerRanking->total_marks)->toBe(70.0);

    // Despite lower total marks, the passer ranks #1 — both failers rank below regardless of their totals.
    expect($passerRanking->class_rank)->toBe(1)
        ->and($highFailerRanking->class_rank)->toBe(2)
        ->and($lowFailerRanking->class_rank)->toBe(3);

    expect($passerRanking->gpa)->toBeGreaterThan(0.0)
        ->and($highFailerRanking->gpa)->toBe(0.0)
        ->and($lowFailerRanking->gpa)->toBe(0.0);
});
