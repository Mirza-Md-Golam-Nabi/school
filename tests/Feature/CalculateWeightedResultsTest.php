<?php

use App\Actions\CalculateExamRankings;
use App\Actions\CalculateWeightedResults;
use App\Enums\CountMethod;
use App\Enums\ExamConfigType;
use App\Enums\ResultRounding;
use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use App\Models\ResultRule;
use App\Models\SchoolSetting;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeWeightedTestClass(string $name = 'Class 9'): Classes
{
    return Classes::create(['name' => $name, 'order' => 9, 'is_active' => true]);
}

function makeWeightedTestSubject(Classes $class): Subject
{
    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    return $subject;
}

function makeWeightedTestStudent(Classes $class): StudentProfile
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

function makeWeightedTestExamType(string $name, CountMethod $countMethod = CountMethod::All, ?int $bestN = null): ExamType
{
    $examType = ExamType::create(['name' => $name, 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $examType->id, 'type' => ExamConfigType::Main, 'count_method' => $countMethod, 'best_n_count' => $bestN]);

    return $examType;
}

function makeWeightedTestExam(Classes $class, ExamType $examType): Exam
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

/**
 * An exam of the type with the subject out of $totalMarks and one student's marks in it.
 */
function makeWeightedTestSitting(Classes $class, ExamType $examType, Subject $subject, StudentProfile $student, float $totalMarks, ?float $marks, array $configAttributes = []): Exam
{
    $exam = makeWeightedTestExam($class, $examType);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => $totalMarks,
        'total_marks' => $totalMarks,
        'pass_mark' => $totalMarks * 0.33,
        ...$configAttributes,
    ]);

    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => $marks,
        'is_absent' => $marks === null,
    ]);

    return $exam;
}

function weightedTestResultOf(Exam $exam, StudentProfile $student): StudentResult
{
    return StudentResult::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
}

/**
 * The semester example: 1st Semester 25% + 2nd Semester 25% + Final 50%, each out of 100.
 *
 * @return array{class: Classes, subject: Subject, student: StudentProfile, first: ExamType, second: ExamType, final: ExamType}
 */
function makeSemesterSetup(): array
{
    $class = makeWeightedTestClass();

    return [
        'class' => $class,
        'subject' => makeWeightedTestSubject($class),
        'student' => makeWeightedTestStudent($class),
        'first' => makeWeightedTestExamType('1st Semester'),
        'second' => makeWeightedTestExamType('2nd Semester'),
        'final' => makeWeightedTestExamType('Final'),
    ];
}

it('makes the final result from 25% of each semester and 50% of the final, out of 100', function () {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    makeResultRule($class, $final, [[$first, 25], [$second, 25], [$final, 50]]);

    makeWeightedTestSitting($class, $first, $subject, $student, 100, 80);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, 70);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($finalExam);

    $result = weightedTestResultOf($finalExam, $student);

    // 80 × 25% + 70 × 25% + 90 × 50% = 20 + 17.5 + 45
    expect($result->final_marks)->toBe(82.5)
        ->and($result->final_full_marks)->toBe(100.0)
        ->and(collect($result->result_breakdown)->pluck('marks')->all())->toBe([20, 17.5, 45])
        ->and(collect($result->result_breakdown)->pluck('name')->all())->toBe(['1st Semester', '2nd Semester', 'Final']);
});

it('rounds only the final marks, the way the rule says', function (ResultRounding $rounding, float $expected) {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    makeResultRule($class, $final, [[$first, 25], [$second, 25], [$final, 50]], ['rounding' => $rounding]);

    makeWeightedTestSitting($class, $first, $subject, $student, 100, 79);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, 70);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($finalExam);

    // 19.75 + 17.5 + 45 = 82.25
    expect(weightedTestResultOf($finalExam, $student)->final_marks)->toBe($expected);
})->with([
    'decimal' => [ResultRounding::Decimal, 82.25],
    'nearest' => [ResultRounding::Nearest, 82.0],
    'always up' => [ResultRounding::Up, 83.0],
]);

it('blends the best 2 of 3 class tests into the half yearly, as the class test 20% + half yearly 80% rule', function () {
    $class = makeWeightedTestClass();
    $subject = makeWeightedTestSubject($class);
    $student = makeWeightedTestStudent($class);
    $classTest = makeWeightedTestExamType('Class Test', CountMethod::BestN, 2);
    $halfYearly = makeWeightedTestExamType('Half Yearly');
    makeResultRule($class, $halfYearly, [[$classTest, 20], [$halfYearly, 80]]);

    // Out of 10: 7, 9, 8 → best 2 = 9 and 8 → 85%
    foreach ([7, 9, 8] as $marks) {
        makeWeightedTestSitting($class, $classTest, $subject, $student, 10, $marks);
    }
    $halfYearlyExam = makeWeightedTestSitting($class, $halfYearly, $subject, $student, 80, 65);

    app(CalculateWeightedResults::class)->execute($halfYearlyExam);

    $result = weightedTestResultOf($halfYearlyExam, $student);
    $classTestPart = collect($result->result_breakdown)->firstWhere('name', 'Class Test');

    // 85% × 20 + 65/80 × 80 = 17 + 65
    expect($result->final_marks)->toBe(82.0)
        ->and($classTestPart['marks'])->toBe(17)
        ->and(collect($classTestPart['exams'])->pluck('marks')->all())->toBe([9, 8]);
});

it('uses the School Settings full marks when the rule gives none', function () {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    makeResultRule($class, $final, [[$first, 25], [$second, 25], [$final, 50]], ['full_marks' => null]);
    SchoolSetting::set(ResultRule::DEFAULT_FULL_MARKS_SETTING, '200');

    makeWeightedTestSitting($class, $first, $subject, $student, 100, 80);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, 70);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($finalExam);

    expect(weightedTestResultOf($finalExam, $student)->final_marks)->toBe(165.0)
        ->and(weightedTestResultOf($finalExam, $student)->final_full_marks)->toBe(200.0);
});

it('counts an exam the student was absent from as zero', function () {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    makeResultRule($class, $final, [[$first, 25], [$second, 25], [$final, 50]]);

    makeWeightedTestSitting($class, $first, $subject, $student, 100, 80);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, null);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($finalExam);

    $result = weightedTestResultOf($finalExam, $student);

    expect($result->final_marks)->toBe(65.0)
        ->and(collect($result->result_breakdown)->firstWhere('name', '2nd Semester')['is_absent'])->toBeTrue();
});

it('shares out the weight of an exam the subject was not examined in', function () {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    makeResultRule($class, $final, [[$first, 25], [$second, 25], [$final, 50]]);

    // The 1st semester exam exists, but this subject was not part of it.
    makeWeightedTestExam($class, $first);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, 70);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($finalExam);

    $result = weightedTestResultOf($finalExam, $student);

    // 2nd semester ⅓, final ⅔: 70/3 + 90 × ⅔ = 23.33 + 60
    expect($result->final_marks)->toBe(83.33)
        ->and(collect($result->result_breakdown)->firstWhere('name', '1st Semester')['held'])->toBeFalse()
        ->and(collect($result->result_breakdown)->firstWhere('name', 'Final')['effective_weight'])->toBe(66.67);
});

it('leaves out an exam whose subject is switched off for the result', function () {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    makeResultRule($class, $final, [[$first, 25], [$second, 25], [$final, 50]]);

    makeWeightedTestSitting($class, $first, $subject, $student, 100, 10, ['contributes_to_target' => false]);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, 70);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($finalExam);

    expect(weightedTestResultOf($finalExam, $student)->final_marks)->toBe(83.33);
});

it('fails the subject when a component that must be passed on its own is not, however high the total', function () {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    makeResultRule($class, $final, [[$first, 25, true], [$second, 25], [$final, 50]]);

    // 20 is below the 1st semester's pass mark of 33.
    makeWeightedTestSitting($class, $first, $subject, $student, 100, 20);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, 100);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 100);

    app(CalculateExamRankings::class)->execute($finalExam);

    $result = weightedTestResultOf($finalExam, $student);
    $ranking = StudentMeritRanking::where('exam_id', $finalExam->id)->where('student_id', $student->id)->firstOrFail();

    // 5 + 25 + 50 = 80, an A+ by marks alone.
    expect($result->final_marks)->toBe(80.0)
        ->and($result->failsARequiredComponent())->toBeTrue()
        ->and($ranking->gpa)->toBe(0.0);
});

it('judges a must-pass component of several exams by their combined percentage', function () {
    $class = makeWeightedTestClass();
    $subject = makeWeightedTestSubject($class);
    $student = makeWeightedTestStudent($class);
    $classTest = makeWeightedTestExamType('Class Test');
    $halfYearly = makeWeightedTestExamType('Half Yearly');
    makeResultRule($class, $halfYearly, [[$classTest, 20, true], [$halfYearly, 80]]);

    // 20% and 40% → 30%, below the 33% pass mark.
    makeWeightedTestSitting($class, $classTest, $subject, $student, 100, 20);
    makeWeightedTestSitting($class, $classTest, $subject, $student, 100, 40);
    $halfYearlyExam = makeWeightedTestSitting($class, $halfYearly, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($halfYearlyExam);

    expect(collect(weightedTestResultOf($halfYearlyExam, $student)->result_breakdown)->firstWhere('name', 'Class Test')['passed'])->toBeFalse();
});

it('fails an exam\'s own result that misses a checked part\'s pass mark, though the total grades a pass', function () {
    $class = makeWeightedTestClass();
    $subject = makeWeightedTestSubject($class);
    $student = makeWeightedTestStudent($class);
    $exam = makeWeightedTestExam($class, makeWeightedTestExamType('Half Yearly'));

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'mcq_total' => 30,
        'written_total' => 70,
        'written_pass_mark' => 23,
        'check_written_pass' => true,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'class_id' => $class->id, 'student_id' => $student->id, 'mcq_marks' => 30, 'written_marks' => 20]);

    app(CalculateExamRankings::class)->execute($exam);

    // 50/100 grades a pass, but written 20 < 23.
    expect(StudentMeritRanking::where('exam_id', $exam->id)->firstOrFail()->gpa)->toBe(0.0);
});

it('ranks the result rule\'s exam by its weighted marks, and other exams by their own', function () {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    makeResultRule($class, $final, [[$first, 25], [$second, 25], [$final, 50]]);

    $firstExam = makeWeightedTestSitting($class, $first, $subject, $student, 100, 80);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, 70);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 90);

    app(CalculateExamRankings::class)->execute($finalExam);
    app(CalculateExamRankings::class)->execute($firstExam);

    expect(StudentMeritRanking::where('exam_id', $finalExam->id)->firstOrFail()->total_marks)->toBe(82.5)
        // The semester keeps its own result out of 100.
        ->and(StudentMeritRanking::where('exam_id', $firstExam->id)->firstOrFail()->total_marks)->toBe(80.0)
        ->and(weightedTestResultOf($firstExam, $student)->final_marks)->toBeNull();
});

it('only applies a rule to the classes attached to it', function () {
    ['subject' => $subject, 'first' => $first, 'second' => $second, 'final' => $final, 'class' => $ruleClass] = makeSemesterSetup();
    makeResultRule($ruleClass, $final, [[$first, 25], [$second, 25], [$final, 50]]);

    $otherClass = makeWeightedTestClass('Class 3');
    $subject->classes()->attach($otherClass->id, ['subject_type' => SubjectType::Compulsory->value]);
    $student = makeWeightedTestStudent($otherClass);
    $finalExam = makeWeightedTestSitting($otherClass, $final, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($finalExam);

    expect(weightedTestResultOf($finalExam, $student)->final_marks)->toBeNull()
        ->and($finalExam->refresh()->result_rule_snapshot)->toBeNull();
});

it('clears an old weighted result when the rule no longer applies', function () {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    $rule = makeResultRule($class, $final, [[$first, 25], [$second, 25], [$final, 50]]);

    makeWeightedTestSitting($class, $first, $subject, $student, 100, 80);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, 70);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($finalExam);
    $rule->delete();
    app(CalculateWeightedResults::class)->execute($finalExam->refresh());

    $result = weightedTestResultOf($finalExam, $student);

    expect($result->final_marks)->toBeNull()
        ->and($result->result_breakdown)->toBeNull()
        ->and($result->effective_marks)->toBe(90.0);
});

it('keeps a published result on the rule it was calculated with, and an unpublished one on the current rule', function () {
    ['class' => $class, 'subject' => $subject, 'student' => $student, 'first' => $first, 'second' => $second, 'final' => $final] = makeSemesterSetup();
    $rule = makeResultRule($class, $final, [[$first, 25], [$second, 25], [$final, 50]]);

    makeWeightedTestSitting($class, $first, $subject, $student, 100, 80);
    makeWeightedTestSitting($class, $second, $subject, $student, 100, 70);
    $finalExam = makeWeightedTestSitting($class, $final, $subject, $student, 100, 90);

    app(CalculateWeightedResults::class)->execute($finalExam);

    // The school changes the rule to 20 / 30 / 50.
    $rule->components()->where('exam_type_id', $first->id)->update(['weight_percent' => 20]);
    $rule->components()->where('exam_type_id', $second->id)->update(['weight_percent' => 30]);

    app(CalculateWeightedResults::class)->execute($finalExam->refresh());
    // 16 + 21 + 45
    expect(weightedTestResultOf($finalExam, $student)->final_marks)->toBe(82.0);

    $finalExam->update(['is_published' => true]);
    $rule->components()->where('exam_type_id', $first->id)->update(['weight_percent' => 40]);
    $rule->components()->where('exam_type_id', $second->id)->update(['weight_percent' => 10]);

    app(CalculateWeightedResults::class)->execute($finalExam->refresh());
    expect(weightedTestResultOf($finalExam, $student)->final_marks)->toBe(82.0);
});
