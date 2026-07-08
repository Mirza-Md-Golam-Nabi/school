<?php

use App\Enums\ExamConfigType;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\StudentResultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedResultTestStudents(Classes $class, int $count): void
{
    for ($rollNo = 1; $rollNo <= $count; $rollNo++) {
        StudentProfile::create([
            'user_id' => User::factory()->create()->id,
            'roll_no' => $rollNo,
            'current_class_id' => $class->id,
            'session_year' => now()->year,
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
    }
}

/**
 * @param  array<string, mixed>  $subjectConfigOverrides
 */
function createResultTestExam(ExamConfigType $type, int $studentCount = 8, array $subjectConfigOverrides = []): Exam
{
    $class = Classes::create(['name' => 'Class 6', 'order' => 6]);
    seedResultTestStudents($class, $studentCount);

    $examType = ExamType::create(['name' => $type->value.' exam type']);
    ExamTypeConfig::create(['exam_type_id' => $examType->id, 'type' => $type]);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now(),
        'end_date' => now()->addDays(5),
        'is_published' => false,
    ]);

    $subject = Subject::create(['name' => 'Mathematics', 'has_mcq' => false]);

    ExamSubjectConfig::create(array_merge([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'mcq_total' => null,
        'mcq_pass_mark' => null,
        'written_total' => 100,
        'written_pass_mark' => 40,
        'practical_total' => null,
        'practical_pass_mark' => null,
        'total_marks' => 100,
        'pass_mark' => 40,
        'check_mcq_pass' => false,
        'check_written_pass' => true,
        'check_practical_pass' => false,
    ], $subjectConfigOverrides));

    return $exam->fresh(['examType.examTypeConfig', 'subjectConfigs']);
}

it('creates one result per student per subject for the exam', function () {
    $exam = createResultTestExam(ExamConfigType::Main, studentCount: 8);

    (new StudentResultSeeder)->run();

    expect(StudentResult::where('exam_id', $exam->id)->count())->toBe(8);
});

it('does not mark any student absent for a main exam', function () {
    $exam = createResultTestExam(ExamConfigType::Main, studentCount: 8);

    (new StudentResultSeeder)->run();

    expect(StudentResult::where('exam_id', $exam->id)->where('is_absent', true)->count())->toBe(0);
});

it('marks two or three students absent for a supporting exam', function () {
    $exam = createResultTestExam(ExamConfigType::Supporting, studentCount: 8);

    (new StudentResultSeeder)->run();

    $absentCount = StudentResult::where('exam_id', $exam->id)->where('is_absent', true)->count();

    expect($absentCount)->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(3);
});

it('marks one or two students absent for a not-supporting exam', function () {
    $exam = createResultTestExam(ExamConfigType::NotSupporting, studentCount: 8);

    (new StudentResultSeeder)->run();

    $absentCount = StudentResult::where('exam_id', $exam->id)->where('is_absent', true)->count();

    expect($absentCount)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(2);
});

it('nulls out marks and zeroes the total for absent students', function () {
    $exam = createResultTestExam(ExamConfigType::Supporting, studentCount: 8);

    (new StudentResultSeeder)->run();

    $absentResults = StudentResult::where('exam_id', $exam->id)->where('is_absent', true)->get();

    expect($absentResults)->not->toBeEmpty();

    foreach ($absentResults as $result) {
        expect($result->mcq_marks)->toBeNull()
            ->and($result->written_marks)->toBeNull()
            ->and($result->practical_marks)->toBeNull()
            ->and((float) $result->total_marks)->toBe(0.0);
    }
});

it('keeps every mark within the exam subject config full marks', function () {
    $exam = createResultTestExam(ExamConfigType::Main, studentCount: 10, subjectConfigOverrides: [
        'mcq_total' => 30,
        'mcq_pass_mark' => 12,
        'written_total' => 70,
        'written_pass_mark' => 28,
        'total_marks' => 100,
        'pass_mark' => 40,
        'check_mcq_pass' => true,
    ]);
    $config = $exam->subjectConfigs->sole();

    (new StudentResultSeeder)->run();

    $results = StudentResult::where('exam_id', $exam->id)->get();

    expect($results)->toHaveCount(10);

    foreach ($results as $result) {
        expect($result->is_absent)->toBeFalse()
            ->and($result->mcq_marks)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual($config->mcq_total)
            ->and($result->written_marks)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual($config->written_total)
            ->and((float) $result->total_marks)->toBeLessThanOrEqual((float) $config->total_marks)
            ->and((float) $result->total_marks)->toBe(round($result->mcq_marks + $result->written_marks, 2));
    }
});

it('fails one to three students on a subject while the majority pass', function () {
    $exam = createResultTestExam(ExamConfigType::Main, studentCount: 10);
    $config = $exam->subjectConfigs->sole();

    (new StudentResultSeeder)->run();

    $results = StudentResult::where('exam_id', $exam->id)->get();
    $failing = $results->filter(fn (StudentResult $result) => $result->total_marks < $config->pass_mark);

    expect($failing->count())->toBeGreaterThanOrEqual(1)
        ->and($failing->count())->toBeLessThanOrEqual(3)
        ->and($failing->count())->toBeLessThan($results->count());
});

it('is idempotent when run twice for the same exam', function () {
    $exam = createResultTestExam(ExamConfigType::Main, studentCount: 6);

    (new StudentResultSeeder)->run();
    (new StudentResultSeeder)->run();

    expect(StudentResult::where('exam_id', $exam->id)->count())->toBe(6);
});
