<?php

use App\Actions\CalculateWeightedResults;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Student\Resources\ExamResults\Pages\ViewExamResult;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('student');
});

function makeMarksDetailTestClass(): Classes
{
    return Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
}

function makeMarksDetailTestSubject(Classes $class, string $name = 'Bangla'): Subject
{
    $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    return $subject;
}

function makeMarksDetailTestStudent(Classes $class): StudentProfile
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

function makeMarksDetailTestExam(Classes $class, ExamType $examType): Exam
{
    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);
}

it('403s when viewing marks detail for a ranking that is not the logged-in student\'s own', function () {
    $class = makeMarksDetailTestClass();
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeMarksDetailTestExam($class, $examType);

    $me = makeMarksDetailTestStudent($class);
    $otherStudent = makeMarksDetailTestStudent($class);

    $otherRanking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $otherStudent->id,
        'class_id' => $class->id,
        'total_marks' => 80,
        'gpa' => 4.0,
    ]);

    $this->actingAs($me->user);

    $component = Livewire::test(ViewExamResult::class, ['record' => $exam])->instance();

    expect(fn () => $component->getMyMarksDetail($otherRanking->id))
        ->toThrow(HttpException::class);
});

it('builds marks-detail rows with plain marks when no result rule applies', function () {
    $class = makeMarksDetailTestClass();
    $subject = makeMarksDetailTestSubject($class);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeMarksDetailTestExam($class, $examType);

    $student = makeMarksDetailTestStudent($class);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 80,
        'total_marks' => 80,
        'pass_mark' => 26,
    ]);

    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 65,
    ]);

    $ranking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 65,
        'gpa' => 4.0,
        'class_rank' => 1,
    ]);

    $this->actingAs($student->user);

    $component = Livewire::test(ViewExamResult::class, ['record' => $exam])->instance();
    $view = $component->getMyMarksDetail($ranking->id);
    $data = $view->getData();

    expect($data['rows'])->toHaveCount(1);

    $row = $data['rows'][0];

    expect($row['subject_name'])->toBe('Bangla')
        ->and($row['total_marks'])->toBe(65.0)
        ->and($row['components'])->toBe([])
        ->and($data['summary']['total_marks'])->toBe(65.0)
        ->and($data['summary']['class_rank'])->toBe(1);
});

it('includes what each result-rule component gave in marks-detail rows when a rule applied', function () {
    $class = makeMarksDetailTestClass();
    $subject = makeMarksDetailTestSubject($class);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    makeResultRule($class, $halfYearlyType, [[$classTestType, 20], [$halfYearlyType, 80]]);

    $student = makeMarksDetailTestStudent($class);

    $classTest = makeMarksDetailTestExam($class, $classTestType);
    ExamSubjectConfig::create([
        'exam_id' => $classTest->id,
        'subject_id' => $subject->id,
        'written_total' => 10,
        'total_marks' => 10,
        'pass_mark' => 4,
    ]);
    StudentResult::create([
        'exam_id' => $classTest->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 9,
    ]);

    $halfYearly = makeMarksDetailTestExam($class, $halfYearlyType);
    ExamSubjectConfig::create([
        'exam_id' => $halfYearly->id,
        'subject_id' => $subject->id,
        'written_total' => 80,
        'total_marks' => 80,
        'pass_mark' => 26,
    ]);
    StudentResult::create([
        'exam_id' => $halfYearly->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 65,
    ]);

    app(CalculateWeightedResults::class)->execute($halfYearly);

    // Class Test 9/10 = 90% of 20 = 18, Half Yearly 65/80 = 81.25% of 80 = 65 → 83 out of 100.
    $ranking = StudentMeritRanking::create([
        'exam_id' => $halfYearly->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 83,
        'gpa' => 5.0,
        'class_rank' => 1,
    ]);

    $this->actingAs($student->user);

    $component = Livewire::test(ViewExamResult::class, ['record' => $halfYearly])->instance();
    $view = $component->getMyMarksDetail($ranking->id);
    $data = $view->getData();

    $row = $data['rows'][0];

    expect($row['total_marks'])->toBe(83.0)
        ->and(collect($row['components'])->map(fn (array $component): array => [$component['name'], $component['weight'], $component['marks']])->all())
        ->toBe([['Class Test', 20.0, 18.0], ['Half Yearly', 80.0, 65.0]]);
});
