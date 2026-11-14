<?php

use App\Actions\BuildExamTabulationSheetDetail;
use App\Actions\BuildStudentMarksDetail;
use App\Actions\CalculateExamRankings;
use App\Actions\CreateExamAction;
use App\Enums\Gender;
use App\Enums\MergedAbsentRule;
use App\Enums\MergedComponentPassRule;
use App\Enums\MergedPassRule;
use App\Enums\ResultRounding;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\SchoolSetting;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\SubjectMerge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->class = Classes::create(['name' => 'Class 8', 'order' => 8, 'is_active' => true]);

    $makeSubject = function (string $name, int $sortOrder): Subject {
        $subject = Subject::create(['name' => $name, 'has_written' => true, 'is_active' => true, 'sort_order' => $sortOrder]);
        $subject->classes()->attach($this->class->id, ['subject_type' => SubjectType::Compulsory->value]);

        return $subject;
    };

    $this->banglaFirst = $makeSubject('Bangla 1st Paper', 1);
    $this->banglaSecond = $makeSubject('Bangla 2nd Paper', 2);
    $this->math = $makeSubject('Math', 3);

    $this->exam = Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Final', 'is_active' => true])->id,
        'class_id' => $this->class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);

    foreach ([$this->banglaFirst, $this->banglaSecond, $this->math] as $subject) {
        ExamSubjectConfig::create(['exam_id' => $this->exam->id, 'subject_id' => $subject->id, 'written_total' => 100, 'total_marks' => 100, 'pass_mark' => 33]);
    }

    $this->exam->subjectMerges()->create([
        'name' => 'Bangla',
        'first_subject_id' => $this->banglaFirst->id,
        'second_subject_id' => $this->banglaSecond->id,
        'pass_rule' => MergedPassRule::Combined,
        'component_pass_rule' => MergedComponentPassRule::Combined,
        'absent_rule' => MergedAbsentRule::Fail,
        'rounding' => ResultRounding::Nearest,
    ]);
});

function subjectMergeTestStudent(Classes $class, int $rollNo): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

/**
 * @param  array<int, float|null>  $marksBySubject  subject_id → written marks (null = absent)
 */
function subjectMergeTestMarks(Exam $exam, StudentProfile $student, array $marksBySubject): void
{
    foreach ($marksBySubject as $subjectId => $marks) {
        StudentResult::create([
            'exam_id' => $exam->id,
            'subject_id' => $subjectId,
            'class_id' => $exam->class_id,
            'student_id' => $student->id,
            'written_marks' => $marks,
            'is_absent' => $marks === null,
        ]);
    }
}

it('counts a merged subject once in the GPA and adds its converted marks to the total', function () {
    $student = subjectMergeTestStudent($this->class, 1);
    // Bangla (60 + 70) ÷ 200 = 65 → A- (3.5); Math 82 → A+ (5).
    subjectMergeTestMarks($this->exam, $student, [$this->banglaFirst->id => 60, $this->banglaSecond->id => 70, $this->math->id => 82]);

    app(CalculateExamRankings::class)->execute($this->exam);

    $ranking = StudentMeritRanking::where('student_id', $student->id)->sole();

    expect((float) $ranking->total_marks)->toBe(147.0)
        ->and((float) $ranking->gpa)->toBe(4.25);
});

it('fails the student overall when the merged subject fails', function () {
    $passer = subjectMergeTestStudent($this->class, 1);
    $failer = subjectMergeTestStudent($this->class, 2);
    subjectMergeTestMarks($this->exam, $passer, [$this->banglaFirst->id => 40, $this->banglaSecond->id => 40, $this->math->id => 40]);
    // Bangla (30 + 30) ÷ 200 = 30 → fails, despite the higher total.
    subjectMergeTestMarks($this->exam, $failer, [$this->banglaFirst->id => 30, $this->banglaSecond->id => 30, $this->math->id => 99]);

    app(CalculateExamRankings::class)->execute($this->exam);

    $failerRanking = StudentMeritRanking::where('student_id', $failer->id)->sole();

    expect((float) $failerRanking->gpa)->toBe(0.0)
        ->and($failerRanking->class_rank)->toBe(2)
        ->and(StudentMeritRanking::where('student_id', $passer->id)->sole()->class_rank)->toBe(1);
});

it('shows a merged subject as one row with both papers in the marks detail', function () {
    $topper = subjectMergeTestStudent($this->class, 1);
    $student = subjectMergeTestStudent($this->class, 2);
    subjectMergeTestMarks($this->exam, $topper, [$this->banglaFirst->id => 80, $this->banglaSecond->id => 82, $this->math->id => 90]);
    subjectMergeTestMarks($this->exam, $student, [$this->banglaFirst->id => 75, $this->banglaSecond->id => 80, $this->math->id => 60]);

    app(CalculateExamRankings::class)->execute($this->exam);

    $rows = app(BuildStudentMarksDetail::class)->handle(StudentMeritRanking::where('student_id', $student->id)->sole())['rows'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['subject_name'])->toBe('Bangla')
        ->and($rows[0]['total_marks'])->toBe(78.0)
        ->and($rows[0]['grade_label'])->toBe('A')
        ->and((float) $rows[0]['grade_point'])->toBe(4.0)
        ->and($rows[0]['best_marks'])->toBe(81.0)
        ->and(collect($rows[0]['papers'])->pluck('total_marks')->all())->toBe([75.0, 80.0])
        ->and($rows[1]['subject_name'])->toBe('Math')
        ->and($rows[1]['papers'])->toBeNull();
});

it('adds each merged paper\'s own marks, not the merged marks, into the obtained marks', function () {
    $topper = subjectMergeTestStudent($this->class, 1);
    $student = subjectMergeTestStudent($this->class, 2);
    subjectMergeTestMarks($this->exam, $topper, [$this->banglaFirst->id => 80, $this->banglaSecond->id => 82, $this->math->id => 90]);
    subjectMergeTestMarks($this->exam, $student, [$this->banglaFirst->id => 75, $this->banglaSecond->id => 80, $this->math->id => 60]);

    app(CalculateExamRankings::class)->execute($this->exam);

    $summary = app(BuildStudentMarksDetail::class)->handle(StudentMeritRanking::where('student_id', $student->id)->sole())['summary'];

    expect($summary['obtained_marks'])->toBe(215.0)
        ->and($summary['obtained_full_marks'])->toBe(300.0)
        ->and($summary['top_rank_obtained_marks'])->toBe(252.0);
});

it('gives a merged subject three tabulation columns: 1st, 2nd and merged', function () {
    $student = subjectMergeTestStudent($this->class, 1);
    subjectMergeTestMarks($this->exam, $student, [$this->banglaFirst->id => 75, $this->banglaSecond->id => 80, $this->math->id => 60]);

    app(CalculateExamRankings::class)->execute($this->exam);

    $detail = app(BuildExamTabulationSheetDetail::class)->handle($this->exam);
    $mergeKey = 'merge:'.$this->exam->subjectMerges()->sole()->id;

    expect($detail['hasMerges'])->toBeTrue()
        ->and($detail['columns']->pluck('key')->all())->toBe([$mergeKey, $this->math->id])
        ->and($detail['columns'][0]['papers'])->toBe([$this->banglaFirst->id, $this->banglaSecond->id])
        ->and($detail['rows'][0]['marks'][$mergeKey]['value'])->toBe(78.0)
        ->and((float) $detail['rows'][0]['total_marks'])->toBe(138.0);
});

it('copies the class merges onto a new exam with School Settings filling empty rules', function () {
    SchoolSetting::set(SubjectMerge::PASS_RULE_SETTING, MergedPassRule::EachPaper->value);

    SubjectMerge::create([
        'class_id' => $this->class->id,
        'name' => 'Bangla',
        'name_bn' => 'বাংলা',
        'first_subject_id' => $this->banglaFirst->id,
        'second_subject_id' => $this->banglaSecond->id,
        'absent_rule' => MergedAbsentRule::CountAsZero,
    ]);

    $exam = app(CreateExamAction::class)->handle([
        'exam_type_id' => ExamType::create(['name' => 'Half Yearly', 'is_active' => true])->id,
        'class_id' => $this->class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    $merge = $exam->subjectMerges()->sole();

    expect($merge->name_bn)->toBe('বাংলা')
        ->and($merge->subjectIds())->toBe([$this->banglaFirst->id, $this->banglaSecond->id])
        ->and($merge->pass_rule)->toBe(MergedPassRule::EachPaper)
        ->and($merge->absent_rule)->toBe(MergedAbsentRule::CountAsZero)
        ->and($merge->rounding)->toBe(ResultRounding::Decimal);
});

it('renders a merged subject on the marksheet and the tabulation sheet', function () {
    $student = subjectMergeTestStudent($this->class, 1);
    subjectMergeTestMarks($this->exam, $student, [$this->banglaFirst->id => 75, $this->banglaSecond->id => null, $this->math->id => 60]);

    app(CalculateExamRankings::class)->execute($this->exam);

    $ranking = StudentMeritRanking::where('student_id', $student->id)->sole();
    ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);

    $marksheetHtml = view('documents.marksheet', [
        'marksheet' => Marksheet::create(['student_id' => $student->id, 'exam_id' => $this->exam->id, 'is_generated' => true]),
        'rows' => $rows,
        'summary' => $summary,
    ])->render();

    $detail = app(BuildExamTabulationSheetDetail::class)->handle($this->exam);
    $tabulationHtml = view($detail['view'], $detail)->render();

    expect($marksheetHtml)->toContain('Bangla')->toContain('1st')->toContain('2nd')->toContain('Absent')->toContain('rowspan="2"')
        ->and($tabulationHtml)->toContain('colspan="3"')->toContain('Bangla');
});
