<?php

use App\Actions\BuildExamTabulationSheetDetail;
use App\Actions\BuildStudentMarksDetail;
use App\Actions\CalculateExamRankings;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamSubjectMerge;
use App\Models\ExamType;
use App\Models\Group;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\SubjectMerge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * Arabic 1st is for every group, Arabic 2nd only for Humanities: a merge of
 * the two applies to Humanities students only; a Science student's Arabic
 * 1st counts as a subject of its own.
 *
 * @return array{exam: Exam, science: Group, humanities: Group, arabicFirst: Subject, arabicSecond: Subject, merge: ExamSubjectMerge}
 */
function makePartialGroupMergeExam(): array
{
    $class = Classes::create(['name' => 'Class 11', 'order' => 11, 'has_group' => true, 'is_active' => true]);
    $science = Group::create(['name' => 'Science', 'is_active' => true]);
    $humanities = Group::create(['name' => 'Humanities', 'is_active' => true]);
    $class->groups()->attach([$science->id, $humanities->id]);

    $makeSubject = fn (string $name): Subject => Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $arabicFirst = $makeSubject('Arabic 1st');
    $arabicSecond = $makeSubject('Arabic 2nd');
    $arabicFirst->classes()->attach($class->id, ['group_id' => null, 'subject_type' => SubjectType::Compulsory->value]);
    $arabicSecond->classes()->attach($class->id, ['group_id' => $humanities->id, 'subject_type' => SubjectType::Compulsory->value]);

    $exam = Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Test Exam', 'is_active' => true])->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    foreach ([$arabicFirst, $arabicSecond] as $subject) {
        ExamSubjectConfig::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'written_total' => 100, 'total_marks' => 100, 'pass_mark' => 33]);
    }

    $merge = $exam->subjectMerges()->create([
        'name' => 'Arabic',
        'first_subject_id' => $arabicFirst->id,
        'second_subject_id' => $arabicSecond->id,
        ...SubjectMerge::defaultRules(),
    ]);

    return compact('exam', 'science', 'humanities', 'arabicFirst', 'arabicSecond', 'merge');
}

/**
 * @param  array<int, float>  $writtenMarksBySubject
 */
function makePartialGroupMergeStudent(Exam $exam, Group $group, int $rollNo, array $writtenMarksBySubject): StudentProfile
{
    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $exam->class_id,
        'current_group_id' => $group->id,
        'session_year' => $exam->session_year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    foreach ($writtenMarksBySubject as $subjectId => $writtenMarks) {
        StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subjectId, 'class_id' => $exam->class_id, 'student_id' => $student->id, 'written_marks' => $writtenMarks]);
    }

    return $student;
}

it('counts a paper alone for a group that does not take the other paper of its merge', function () {
    ['exam' => $exam, 'science' => $science, 'humanities' => $humanities, 'arabicFirst' => $arabicFirst, 'arabicSecond' => $arabicSecond] = makePartialGroupMergeExam();

    $scienceStudent = makePartialGroupMergeStudent($exam, $science, 1, [$arabicFirst->id => 80]);
    $humanitiesStudent = makePartialGroupMergeStudent($exam, $humanities, 2, [$arabicFirst->id => 80, $arabicSecond->id => 60]);

    app(CalculateExamRankings::class)->execute($exam);

    $rankingOf = fn (StudentProfile $student): StudentMeritRanking => StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $student->id)->sole();

    // Science: Arabic 1st alone (80 → A+), not a merge missing its 2nd paper.
    expect($rankingOf($scienceStudent))
        ->gpa->toBe(5.0)
        ->total_marks->toBe(80.0)
        ->and($rankingOf($humanitiesStudent))
        ->gpa->toBe(4.0)
        ->total_marks->toBe(70.0);
});

it('shows the paper alone on the marksheet and tabulation sheet of a group that takes only it', function () {
    ['exam' => $exam, 'science' => $science, 'humanities' => $humanities, 'arabicFirst' => $arabicFirst, 'arabicSecond' => $arabicSecond, 'merge' => $merge] = makePartialGroupMergeExam();

    $scienceStudent = makePartialGroupMergeStudent($exam, $science, 1, [$arabicFirst->id => 80]);
    $humanitiesStudent = makePartialGroupMergeStudent($exam, $humanities, 2, [$arabicFirst->id => 80, $arabicSecond->id => 60]);

    app(CalculateExamRankings::class)->execute($exam);

    $rowsOf = fn (StudentProfile $student): Collection => app(BuildStudentMarksDetail::class)
        ->handle(StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $student->id)->sole())['rows'];

    expect($rowsOf($scienceStudent)->sole())
        ->subject_name->toBe('Arabic 1st')
        ->papers->toBeNull()
        ->and($rowsOf($humanitiesStudent)->sole())
        ->subject_name->toBe('Arabic')
        ->papers->toHaveCount(2);

    $blocks = collect(app(BuildExamTabulationSheetDetail::class)->handle($exam)['blocks'])->keyBy(fn (array $block): string => $block['group']->name);

    expect($blocks['Science']['columns']->pluck('key')->all())->toBe([$arabicFirst->id])
        ->and($blocks['Humanities']['columns']->pluck('key')->all())->toBe(["merge:{$merge->id}"]);
});

it('still applies the absent rule when a student of a group taking both papers misses one', function () {
    ['exam' => $exam, 'humanities' => $humanities, 'arabicFirst' => $arabicFirst] = makePartialGroupMergeExam();

    $student = makePartialGroupMergeStudent($exam, $humanities, 1, [$arabicFirst->id => 80]);

    app(CalculateExamRankings::class)->execute($exam);

    // School default absent rule is "fail".
    expect(StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $student->id)->sole()->gpa)->toBe(0.0);
});
