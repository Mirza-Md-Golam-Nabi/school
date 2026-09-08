<?php

use App\Actions\BuildExamTabulationSheetDetail;
use App\Actions\BuildExamTabulationSheetPdfAction;
use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Group;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

function makeTabulationStudent(Classes $class, int $rollNo, ?int $groupId = null): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'current_group_id' => $groupId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makeTabulationSubject(Classes $class, string $name, ?int $groupId, SubjectType $type): Subject
{
    $subject = Subject::create(['name' => $name, 'has_mcq' => false]);

    ClassGroupSubject::create([
        'class_id' => $class->id,
        'group_id' => $groupId,
        'subject_id' => $subject->id,
        'subject_type' => $type,
    ]);

    return $subject;
}

function recordTabulationResult(Exam $exam, Subject $subject, StudentProfile $student, float $marks): void
{
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $exam->class_id,
        'student_id' => $student->id,
        'written_marks' => $marks,
        'total_marks' => $marks,
        'is_absent' => false,
    ]);
}

function recordTabulationRanking(Exam $exam, StudentProfile $student, float $totalMarks, int $rank): void
{
    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $exam->class_id,
        'total_marks' => $totalMarks,
        'gpa' => 5.0,
        'class_rank' => $rank,
    ]);
}

function makeTabulationExam(Classes $class): Exam
{
    $examType = ExamType::create(['name' => 'Half Yearly '.$class->id]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now(),
        'end_date' => now()->addDays(5),
        'is_published' => false,
    ]);
}

it('builds a flat single-table layout for a primary class', function () {
    $class = Classes::create(['name' => 'Class 2', 'level' => ClassLevel::Primary, 'order' => 2, 'has_group' => false]);
    $exam = makeTabulationExam($class);

    $bangla = makeTabulationSubject($class, 'Bangla', null, SubjectType::Compulsory);
    $english = makeTabulationSubject($class, 'English', null, SubjectType::Compulsory);

    foreach ([$bangla, $english] as $subject) {
        ExamSubjectConfig::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'written_total' => 100,
            'total_marks' => 100,
            'pass_mark' => 40,
        ]);
    }

    $student = makeTabulationStudent($class, 1);
    recordTabulationResult($exam, $bangla, $student, 80);
    recordTabulationResult($exam, $english, $student, 70);
    recordTabulationRanking($exam, $student, 150, 1);

    $detail = app(BuildExamTabulationSheetDetail::class)->handle($exam);

    expect($detail['isEmpty'])->toBeFalse()
        ->and($detail['view'])->toBe('documents.tabulation-sheet-primary')
        ->and($detail['orientation'])->toBe('P')
        ->and($detail['subjects']->pluck('name')->sort()->values()->all())->toBe(['Bangla', 'English'])
        ->and($detail['rows'])->toHaveCount(1)
        ->and($detail['rows'][0]['total_marks'])->toBe(150.0);
});

it('builds a flat landscape layout for a class 6-8 style class (no groups)', function () {
    $class = Classes::create(['name' => 'Class 7', 'level' => ClassLevel::Secondary, 'order' => 7, 'has_group' => false]);
    $exam = makeTabulationExam($class);

    $subject = makeTabulationSubject($class, 'Mathematics', null, SubjectType::Compulsory);
    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 40,
    ]);

    $student = makeTabulationStudent($class, 1);
    recordTabulationResult($exam, $subject, $student, 90);
    recordTabulationRanking($exam, $student, 90, 1);

    $detail = app(BuildExamTabulationSheetDetail::class)->handle($exam);

    expect($detail['view'])->toBe('documents.tabulation-sheet-secondary')
        ->and($detail['orientation'])->toBe('L');
});

it('splits class 9-10 into one block per group with only that group\'s own subjects', function () {
    $class = Classes::create(['name' => 'Class 9', 'level' => ClassLevel::Secondary, 'order' => 9, 'has_group' => true]);
    $science = Group::create(['name' => 'Science']);
    $commerce = Group::create(['name' => 'Commerce']);
    $class->groups()->attach([$science->id, $commerce->id]);

    $exam = makeTabulationExam($class);

    $bangla = makeTabulationSubject($class, 'Bangla', null, SubjectType::Compulsory);
    $physics = makeTabulationSubject($class, 'Physics', $science->id, SubjectType::Compulsory);
    $biology = makeTabulationSubject($class, 'Biology', $science->id, SubjectType::Optional);
    $accounting = makeTabulationSubject($class, 'Accounting', $commerce->id, SubjectType::Compulsory);

    foreach ([$bangla, $physics, $biology, $accounting] as $subject) {
        ExamSubjectConfig::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'written_total' => 100,
            'total_marks' => 100,
            'pass_mark' => 40,
        ]);
    }

    $scienceStudent = makeTabulationStudent($class, 1, $science->id);
    recordTabulationResult($exam, $bangla, $scienceStudent, 80);
    recordTabulationResult($exam, $physics, $scienceStudent, 75);
    // Science student did not take Biology (no StudentResult row for it).
    recordTabulationRanking($exam, $scienceStudent, 155, 1);

    $commerceStudent = makeTabulationStudent($class, 2, $commerce->id);
    recordTabulationResult($exam, $bangla, $commerceStudent, 70);
    recordTabulationResult($exam, $accounting, $commerceStudent, 65);
    recordTabulationRanking($exam, $commerceStudent, 135, 2);

    $detail = app(BuildExamTabulationSheetDetail::class)->handle($exam);

    expect($detail['view'])->toBe('documents.tabulation-sheet-secondary-grouped')
        ->and($detail['orientation'])->toBe('L')
        ->and($detail['blocks'])->toHaveCount(2);

    $scienceBlock = collect($detail['blocks'])->firstWhere(fn (array $b) => $b['group']->name === 'Science');
    $commerceBlock = collect($detail['blocks'])->firstWhere(fn (array $b) => $b['group']->name === 'Commerce');

    expect($scienceBlock['subjects']->pluck('name')->sort()->values()->all())->toBe(['Bangla', 'Physics'])
        ->and($scienceBlock['rows'])->toHaveCount(1)
        ->and($commerceBlock['subjects']->pluck('name')->sort()->values()->all())->toBe(['Accounting', 'Bangla'])
        ->and($commerceBlock['rows'])->toHaveCount(1);
});

it('aborts with 404 when rankings have not been calculated yet', function () {
    $class = Classes::create(['name' => 'Class 3', 'level' => ClassLevel::Primary, 'order' => 3, 'has_group' => false]);
    $exam = makeTabulationExam($class);

    expect(fn () => app(BuildExamTabulationSheetPdfAction::class)->handle($exam))
        ->toThrow(NotFoundHttpException::class);
});

it('downloads a tabulation sheet PDF for an authenticated user', function () {
    $class = Classes::create(['name' => 'Class 4', 'level' => ClassLevel::Primary, 'order' => 4, 'has_group' => false]);
    $exam = makeTabulationExam($class);

    $subject = makeTabulationSubject($class, 'Bangla', null, SubjectType::Compulsory);
    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 40,
    ]);

    $student = makeTabulationStudent($class, 1);
    recordTabulationResult($exam, $subject, $student, 80);
    recordTabulationRanking($exam, $student, 80, 1);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('exams.tabulation-sheet.download', ['exam' => $exam]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
});
