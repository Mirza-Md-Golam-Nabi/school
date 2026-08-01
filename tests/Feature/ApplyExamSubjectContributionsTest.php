<?php

use App\Actions\ApplyExamSubjectContributions;
use App\Actions\CalculateExamRankings;
use App\Enums\CountMethod;
use App\Enums\ExamConfigType;
use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamContributeRule;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeContributionTestClass(): Classes
{
    return Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
}

function makeContributionTestSubject(Classes $class): Subject
{
    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    return $subject;
}

function makeContributionTestStudent(Classes $class): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => random_int(1, 999),
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => 'male',
        'status' => 'active',
    ]);
}

function makeContributionTestExam(Classes $class, ExamType $examType): Exam
{
    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);
}

function makeContributionTestSubjectConfig(Exam $exam, Subject $subject, float $totalMarks, bool $contributesToTarget = true): ExamSubjectConfig
{
    return ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => $totalMarks,
        'total_marks' => $totalMarks,
        'pass_mark' => $totalMarks * 0.33,
        'contributes_to_target' => $contributesToTarget,
    ]);
}

function makeContributionTestResult(Exam $exam, Subject $subject, Classes $class, StudentProfile $student, ?float $marks, bool $isAbsent = false): StudentResult
{
    return StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => $isAbsent ? null : $marks,
        'is_absent' => $isAbsent,
    ]);
}

it('blends best-2-of-3 source scores into the target using the exact numbers the user verified by hand', function () {
    $class = makeContributionTestClass();
    $subject = makeContributionTestSubject($class);
    $student = makeContributionTestStudent($class);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $classTestType->id, 'type' => ExamConfigType::Supporting, 'count_method' => CountMethod::BestN, 'best_n_count' => 2]);

    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    // 3 Class Test instances, each out of 10: scores 7, 9, 8 → best 2 average = 85%
    foreach ([7, 9, 8] as $marks) {
        $classTest = makeContributionTestExam($class, $classTestType);
        makeContributionTestSubjectConfig($classTest, $subject, 10);
        makeContributionTestResult($classTest, $subject, $class, $student, $marks);
    }

    $halfYearly = makeContributionTestExam($class, $halfYearlyType);
    makeContributionTestSubjectConfig($halfYearly, $subject, 80);
    makeContributionTestResult($halfYearly, $subject, $class, $student, 65);

    app(ApplyExamSubjectContributions::class)->execute($halfYearly);

    $result = StudentResult::where('exam_id', $halfYearly->id)->where('student_id', $student->id)->first();

    expect($result->contribution_percent)->toBe(20)
        ->and($result->contributed_marks)->toBe(17.0)
        ->and($result->final_marks)->toBe(82.0)
        ->and($result->contribution_source_breakdown)->toHaveCount(2)
        ->and(collect($result->contribution_source_breakdown)->pluck('marks')->sort()->values()->all())->toBe([8, 9]);
});

it('picks the correct best-2 scores out of 4, excluding the lowest two', function () {
    $class = makeContributionTestClass();
    $subject = makeContributionTestSubject($class);
    $student = makeContributionTestStudent($class);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $classTestType->id, 'type' => ExamConfigType::Supporting, 'count_method' => CountMethod::BestN, 'best_n_count' => 2]);

    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 25,
        'session_year' => now()->year,
    ]);

    // 4 Class Tests out of 25: 23, 15, 18, 20 → best 2 = 23, 20
    foreach ([23, 15, 18, 20] as $marks) {
        $classTest = makeContributionTestExam($class, $classTestType);
        makeContributionTestSubjectConfig($classTest, $subject, 25);
        makeContributionTestResult($classTest, $subject, $class, $student, $marks);
    }

    $halfYearly = makeContributionTestExam($class, $halfYearlyType);
    makeContributionTestSubjectConfig($halfYearly, $subject, 75);
    makeContributionTestResult($halfYearly, $subject, $class, $student, 70);

    app(ApplyExamSubjectContributions::class)->execute($halfYearly);

    $result = StudentResult::where('exam_id', $halfYearly->id)->where('student_id', $student->id)->first();

    expect(collect($result->contribution_source_breakdown)->pluck('marks')->sort()->values()->all())->toBe([20, 23]);
});

it('excludes a source instance from the pool when its subject config has contributes_to_target off', function () {
    $class = makeContributionTestClass();
    $subject = makeContributionTestSubject($class);
    $student = makeContributionTestStudent($class);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $classTestType->id, 'type' => ExamConfigType::Supporting, 'count_method' => CountMethod::BestN, 'best_n_count' => 2]);

    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    // Highest scorer (9/10) is explicitly excluded via contributes_to_target = false.
    $excluded = makeContributionTestExam($class, $classTestType);
    makeContributionTestSubjectConfig($excluded, $subject, 10, contributesToTarget: false);
    makeContributionTestResult($excluded, $subject, $class, $student, 9);

    foreach ([7, 8] as $marks) {
        $classTest = makeContributionTestExam($class, $classTestType);
        makeContributionTestSubjectConfig($classTest, $subject, 10);
        makeContributionTestResult($classTest, $subject, $class, $student, $marks);
    }

    $halfYearly = makeContributionTestExam($class, $halfYearlyType);
    makeContributionTestSubjectConfig($halfYearly, $subject, 80);
    makeContributionTestResult($halfYearly, $subject, $class, $student, 65);

    app(ApplyExamSubjectContributions::class)->execute($halfYearly);

    $result = StudentResult::where('exam_id', $halfYearly->id)->where('student_id', $student->id)->first();

    // Only 7 and 8 remain eligible (9 excluded) → best-2 average = 75%.
    expect(collect($result->contribution_source_breakdown)->pluck('marks')->sort()->values()->all())->toBe([7, 8]);
});

it('excludes an absent source instance from the pool instead of counting it as zero', function () {
    $class = makeContributionTestClass();
    $subject = makeContributionTestSubject($class);
    $student = makeContributionTestStudent($class);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $classTestType->id, 'type' => ExamConfigType::Supporting, 'count_method' => CountMethod::BestN, 'best_n_count' => 2]);

    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    // 3 attended, 1 absent (best_n_count = 2, but only 1 present score exists after exclusions below).
    $absentExam = makeContributionTestExam($class, $classTestType);
    makeContributionTestSubjectConfig($absentExam, $subject, 25);
    makeContributionTestResult($absentExam, $subject, $class, $student, null, isAbsent: true);

    $absentExam2 = makeContributionTestExam($class, $classTestType);
    makeContributionTestSubjectConfig($absentExam2, $subject, 25);
    makeContributionTestResult($absentExam2, $subject, $class, $student, null, isAbsent: true);

    $absentExam3 = makeContributionTestExam($class, $classTestType);
    makeContributionTestSubjectConfig($absentExam3, $subject, 25);
    makeContributionTestResult($absentExam3, $subject, $class, $student, null, isAbsent: true);

    $presentExam = makeContributionTestExam($class, $classTestType);
    makeContributionTestSubjectConfig($presentExam, $subject, 25);
    makeContributionTestResult($presentExam, $subject, $class, $student, 22);

    $halfYearly = makeContributionTestExam($class, $halfYearlyType);
    makeContributionTestSubjectConfig($halfYearly, $subject, 75);
    makeContributionTestResult($halfYearly, $subject, $class, $student, 60);

    app(ApplyExamSubjectContributions::class)->execute($halfYearly);

    $result = StudentResult::where('exam_id', $halfYearly->id)->where('student_id', $student->id)->first();

    // Only the single present score (22/25 = 88%) is averaged — not padded with zeros for the 3 absences.
    expect($result->contribution_source_breakdown)->toHaveCount(1)
        ->and($result->contribution_source_breakdown[0]['marks'])->toBe(22)
        ->and($result->contributed_marks)->toBe(round(0.88 * (75 / 0.8 - 75), 2));
});

it('leaves final_marks null when there is no eligible source data, so ranking falls back to total_marks', function () {
    $class = makeContributionTestClass();
    $subject = makeContributionTestSubject($class);
    $student = makeContributionTestStudent($class);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    // No Class Test exams exist at all for this class/session.
    $halfYearly = makeContributionTestExam($class, $halfYearlyType);
    makeContributionTestSubjectConfig($halfYearly, $subject, 80);
    makeContributionTestResult($halfYearly, $subject, $class, $student, 65);

    app(ApplyExamSubjectContributions::class)->execute($halfYearly);

    $result = StudentResult::where('exam_id', $halfYearly->id)->where('student_id', $student->id)->first();

    expect($result->final_marks)->toBeNull()
        ->and($result->effective_marks)->toBe(65.0);
});

it('does nothing when the target exam has no active contribution rule', function () {
    $class = makeContributionTestClass();
    $subject = makeContributionTestSubject($class);
    $student = makeContributionTestStudent($class);

    $standaloneType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeContributionTestExam($class, $standaloneType);
    makeContributionTestSubjectConfig($exam, $subject, 80);
    makeContributionTestResult($exam, $subject, $class, $student, 65);

    app(ApplyExamSubjectContributions::class)->execute($exam);

    $result = StudentResult::where('exam_id', $exam->id)->where('student_id', $student->id)->first();

    expect($result->final_marks)->toBeNull()
        ->and($result->contribution_percent)->toBeNull();
});

it('calculates rankings using contribution-blended final marks when a rule is active', function () {
    $class = makeContributionTestClass();
    $subject = makeContributionTestSubject($class);
    $student = makeContributionTestStudent($class);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    $classTest = makeContributionTestExam($class, $classTestType);
    makeContributionTestSubjectConfig($classTest, $subject, 10);
    makeContributionTestResult($classTest, $subject, $class, $student, 9);

    $halfYearly = makeContributionTestExam($class, $halfYearlyType);
    makeContributionTestSubjectConfig($halfYearly, $subject, 80);
    makeContributionTestResult($halfYearly, $subject, $class, $student, 65);

    app(CalculateExamRankings::class)->execute($halfYearly);

    $ranking = StudentMeritRanking::where('exam_id', $halfYearly->id)->where('student_id', $student->id)->first();

    // own% = 65/80 = 81.25, source% = 9/10 = 90, blended = 81.25*0.8 + 90*0.2 = 83.
    expect($ranking)->not->toBeNull()
        ->and($ranking->total_marks)->toBe(83.0);
});

it('ranks using plain total_marks when no contribution rule applies, unchanged from before', function () {
    $class = makeContributionTestClass();
    $subject = makeContributionTestSubject($class);
    $student = makeContributionTestStudent($class);

    $standaloneType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeContributionTestExam($class, $standaloneType);
    makeContributionTestSubjectConfig($exam, $subject, 80);
    makeContributionTestResult($exam, $subject, $class, $student, 65);

    app(CalculateExamRankings::class)->execute($exam);

    $ranking = StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $student->id)->first();

    expect($ranking)->not->toBeNull()
        ->and($ranking->total_marks)->toBe(65.0);
});
