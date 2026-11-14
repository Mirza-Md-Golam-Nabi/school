<?php

use App\Actions\BuildExamResultReport;
use App\Actions\BuildExamResultReportPdfAction;
use App\Actions\CalculateExamRankings;
use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\ExamReportRelationManager;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

/**
 * An exam with three compulsory 100-mark subjects (pass mark 33).
 *
 * @return array{0: Exam, 1: list<Subject>}
 */
function makeResultReportExam(): array
{
    $class = Classes::create(['name' => 'Class 7', 'level' => ClassLevel::Secondary, 'order' => 7, 'has_group' => false]);
    $examType = ExamType::create(['name' => 'Annual']);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now(),
        'end_date' => now()->addDays(5),
        'is_published' => false,
    ]);

    $subjects = [];

    foreach (['Bangla', 'English', 'Math'] as $name) {
        $subject = Subject::create(['name' => $name, 'has_mcq' => false]);

        ClassGroupSubject::create([
            'class_id' => $class->id,
            'group_id' => null,
            'subject_id' => $subject->id,
            'subject_type' => SubjectType::Compulsory,
        ]);

        ExamSubjectConfig::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'written_total' => 100,
            'total_marks' => 100,
            'pass_mark' => 33,
        ]);

        $subjects[] = $subject;
    }

    return [$exam, $subjects];
}

/**
 * @param  list<Subject>  $subjects
 * @param  list<float>  $marks
 */
function addResultReportStudent(Exam $exam, array $subjects, array $marks, ?int $sectionId = null): StudentProfile
{
    $student = StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => random_int(1, 9999),
        'current_class_id' => $exam->class_id,
        'current_section_id' => $sectionId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    foreach ($subjects as $index => $subject) {
        StudentResult::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'class_id' => $exam->class_id,
            'student_id' => $student->id,
            'written_marks' => $marks[$index],
        ]);
    }

    return $student;
}

/**
 * @param  list<array{label: string, total: int}>  $rows
 * @return array<string, int>
 */
function resultReportTotals(array $rows): array
{
    return collect($rows)->mapWithKeys(fn (array $row): array => [$row['label'] => $row['total']])->all();
}

it('counts students per GPA band, passed/failed, and failures by number of failed subjects', function () {
    [$exam, $subjects] = makeResultReportExam();

    addResultReportStudent($exam, $subjects, [85, 90, 95]);   // GPA 5.00
    addResultReportStudent($exam, $subjects, [82, 88, 81]);   // GPA 5.00
    addResultReportStudent($exam, $subjects, [85, 75, 75]);   // GPA 4.33
    addResultReportStudent($exam, $subjects, [65, 65, 65]);   // GPA 3.50
    addResultReportStudent($exam, $subjects, [85, 85, 10]);   // failed in 1
    addResultReportStudent($exam, $subjects, [10, 20, 85]);   // failed in 2
    addResultReportStudent($exam, $subjects, [10, 20, 30]);   // failed in 3

    app(CalculateExamRankings::class)->execute($exam);

    $report = app(BuildExamResultReport::class)->handle($exam);

    expect($report['isEmpty'])->toBeFalse()
        ->and($report['columns'])->toBe([])
        ->and($report['totalRow']['total'])->toBe(7)
        ->and($report['passedRow']['total'])->toBe(4)
        ->and($report['failedRow']['total'])->toBe(3)
        ->and($report['passRate'])->toBe(57.14);

    expect(resultReportTotals($report['gradeRows']))->toBe([
        'GPA 5.00 (A+)' => 2,
        'GPA 4.00 - 4.99 (A)' => 1,
        'GPA 3.50 - 3.99 (A-)' => 1,
        'GPA 3.00 - 3.49 (B)' => 0,
        'GPA 2.00 - 2.99 (C)' => 0,
        'GPA 1.00 - 1.99 (D)' => 0,
    ]);

    expect(resultReportTotals($report['failRows']))->toBe([
        'Failed in 1 subject' => 1,
        'Failed in 2 subjects' => 1,
        'Failed in 3 subjects' => 1,
    ]);
});

it('breaks the counts down by section when the class has sections', function () {
    [$exam, $subjects] = makeResultReportExam();

    $sectionA = Section::create(['class_id' => $exam->class_id, 'name' => 'A']);
    $sectionB = Section::create(['class_id' => $exam->class_id, 'name' => 'B']);

    addResultReportStudent($exam, $subjects, [85, 90, 95], $sectionA->id);
    addResultReportStudent($exam, $subjects, [85, 85, 10], $sectionA->id);
    addResultReportStudent($exam, $subjects, [90, 90, 90], $sectionB->id);

    app(CalculateExamRankings::class)->execute($exam);

    $report = app(BuildExamResultReport::class)->handle($exam);

    expect($report['breakdownLabel'])->toBe('Section')
        ->and(collect($report['columns'])->pluck('label')->all())->toBe(['A', 'B'])
        ->and($report['gradeRows'][0]['by'])->toBe([$sectionA->id => 1, $sectionB->id => 1])
        ->and($report['failedRow']['by'])->toBe([$sectionA->id => 1, $sectionB->id => 0]);
});

it('is empty until rankings have been calculated', function () {
    [$exam, $subjects] = makeResultReportExam();
    addResultReportStudent($exam, $subjects, [85, 90, 95]);

    expect(app(BuildExamResultReport::class)->handle($exam)['isEmpty'])->toBeTrue()
        ->and(fn () => app(BuildExamResultReportPdfAction::class)->handle($exam))->toThrow(NotFoundHttpException::class);
});

it('downloads the exam result report PDF for an authenticated user', function () {
    [$exam, $subjects] = makeResultReportExam();
    addResultReportStudent($exam, $subjects, [85, 90, 95]);
    addResultReportStudent($exam, $subjects, [85, 85, 10]);
    app(CalculateExamRankings::class)->execute($exam);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('exams.result-report.download', ['exam' => $exam]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
});

it('shows the report and its download button on the Exam Report tab', function () {
    test()->actingAs(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    [$exam, $subjects] = makeResultReportExam();
    addResultReportStudent($exam, $subjects, [85, 90, 95]);
    addResultReportStudent($exam, $subjects, [85, 85, 10]);
    app(CalculateExamRankings::class)->execute($exam);

    Livewire::test(ExamReportRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->assertOk()
        ->assertSee('GPA 5.00 (A+)')
        ->assertSee('Failed in 1 subject')
        ->assertSee('Download Report (PDF)')
        ->assertSeeHtml(route('exams.result-report.download', ['exam' => $exam]));
});

it('counts how many students failed in each subject, in subject order', function () {
    [$exam, $subjects] = makeResultReportExam();

    addResultReportStudent($exam, $subjects, [85, 90, 95]);   // passed
    addResultReportStudent($exam, $subjects, [85, 85, 10]);   // Math
    addResultReportStudent($exam, $subjects, [10, 20, 85]);   // Bangla + English
    addResultReportStudent($exam, $subjects, [10, 85, 20]);   // Bangla + Math

    app(CalculateExamRankings::class)->execute($exam);

    $report = app(BuildExamResultReport::class)->handle($exam);

    expect(resultReportTotals($report['subjectRows']))->toBe([
        'Bangla' => 2,
        'English' => 1,
        'Math' => 2,
    ]);
});

it('puts the subject-wise report on its own second page of the PDF', function () {
    [$exam, $subjects] = makeResultReportExam();
    addResultReportStudent($exam, $subjects, [85, 90, 95]);
    addResultReportStudent($exam, $subjects, [85, 85, 10]);
    app(CalculateExamRankings::class)->execute($exam);

    $pdf = app(BuildExamResultReportPdfAction::class)->handle($exam);

    expect(preg_match_all('#/Type /Page\b(?!s)#', $pdf))->toBe(2);
});
