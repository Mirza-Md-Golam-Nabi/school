<?php

use App\Actions\CalculateExamRankings;
use App\Actions\CopySubjectMergesToExamAction;
use App\Actions\ResolveEligibleStudentsForSubject;
use App\Enums\Gender;
use App\Enums\OptionalSubjectRole;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\SubjectConfigsRelationManager;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamSubjectMerge;
use App\Models\ExamType;
use App\Models\Group;
use App\Models\StudentMeritRanking;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * College subjects have papers (Biology → Biology 1st / 2nd Paper). The class
 * syllabus and a student's optional choice name only the main subject; the
 * papers follow it.
 */
function makeCollegePapersClass(): array
{
    $class = Classes::create(['name' => 'Class 11', 'order' => 11, 'has_group' => true, 'is_active' => true]);
    $science = Group::create(['name' => 'Science', 'is_active' => true]);
    $class->groups()->attach($science->id);

    return [$class, $science];
}

function makeCollegePapersSubject(string $name, ?Subject $parent = null): Subject
{
    return Subject::create([
        'name' => $name,
        'parent_subject_id' => $parent?->id,
        'has_mcq' => false,
        'has_written' => true,
        'has_practical' => false,
        'is_active' => true,
    ]);
}

/**
 * A main subject in the class syllabus with its two papers (which are not).
 *
 * @return array{0: Subject, 1: Subject, 2: Subject}
 */
function makeCollegePapersMainSubject(Classes $class, ?Group $group, string $name, SubjectType $type): array
{
    $main = makeCollegePapersSubject($name);
    $main->classes()->attach($class->id, ['group_id' => $group?->id, 'subject_type' => $type->value]);

    return [$main, makeCollegePapersSubject("{$name} 1st Paper", $main), makeCollegePapersSubject("{$name} 2nd Paper", $main)];
}

function makeCollegePapersStudent(Classes $class, Group $group): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => random_int(1, 999),
        'current_class_id' => $class->id,
        'current_group_id' => $group->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function chooseCollegePapersOptional(StudentProfile $student, Subject $subject, OptionalSubjectRole $role): void
{
    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $student->current_class_id,
        'group_id' => $student->current_group_id,
        'subject_id' => $subject->id,
        'role' => $role,
    ]);
}

function makeCollegePapersExam(Classes $class): Exam
{
    return Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Test Exam', 'is_active' => true])->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);
}

function addCollegePapersConfig(Exam $exam, Subject $subject): ExamSubjectConfig
{
    return ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);
}

it('gives a paper its main subject\'s syllabus records', function () {
    [$class, $science] = makeCollegePapersClass();
    [$biology, $biologyFirst, $biologySecond] = makeCollegePapersMainSubject($class, $science, 'Biology', SubjectType::Optional);

    $records = ClassGroupSubject::recordsBySubjectForClass($class->id);

    expect($records->get($biologyFirst->id)->first()->subject_id)->toBe($biology->id)
        ->and($records->get($biologySecond->id)->first()->subject_type)->toBe(SubjectType::Optional)
        ->and(Subject::taughtInClass($class->id)->pluck('id')->sort()->values()->all())
        ->toBe([$biology->id, $biologyFirst->id, $biologySecond->id]);
});

it('lists a student on both papers\' marks sheets when they chose the main subject', function () {
    [$class, $science] = makeCollegePapersClass();
    [$biology, $biologyFirst, $biologySecond] = makeCollegePapersMainSubject($class, $science, 'Biology', SubjectType::Optional);
    [, $physicsFirst] = makeCollegePapersMainSubject($class, $science, 'Physics', SubjectType::Compulsory);

    $chose = makeCollegePapersStudent($class, $science);
    $didNotChoose = makeCollegePapersStudent($class, $science);
    chooseCollegePapersOptional($chose, $biology, OptionalSubjectRole::MainOptional);

    $resolver = app(ResolveEligibleStudentsForSubject::class);

    expect($resolver->execute($class->id, $biologyFirst->id)->pluck('id')->all())->toBe([$chose->id])
        ->and($resolver->execute($class->id, $biologySecond->id)->pluck('id')->all())->toBe([$chose->id])
        ->and($resolver->execute($class->id, $physicsFirst->id)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$chose->id, $didNotChoose->id])->sort()->values()->all());
});

it('merges an exam\'s two papers of one subject automatically and unmerges when a paper leaves', function () {
    [$class, $science] = makeCollegePapersClass();
    [, $biologyFirst, $biologySecond] = makeCollegePapersMainSubject($class, $science, 'Biology', SubjectType::Optional);
    $exam = makeCollegePapersExam($class);

    addCollegePapersConfig($exam, $biologyFirst);

    expect($exam->subjectMerges()->count())->toBe(0);

    $secondConfig = addCollegePapersConfig($exam, $biologySecond);
    $merge = $exam->subjectMerges()->sole();

    expect($merge->name)->toBe('Biology')
        ->and($merge->subjectIds())->toBe([$biologyFirst->id, $biologySecond->id]);

    $secondConfig->delete();

    expect($exam->subjectMerges()->count())->toBe(0);

    $secondConfig->restore();

    expect($exam->subjectMerges()->count())->toBe(1);
});

it('keeps the paper merge when the class default merges are applied to the exam again', function () {
    [$class, $science] = makeCollegePapersClass();
    [, $biologyFirst, $biologySecond] = makeCollegePapersMainSubject($class, $science, 'Biology', SubjectType::Optional);
    $exam = makeCollegePapersExam($class);
    addCollegePapersConfig($exam, $biologyFirst);
    addCollegePapersConfig($exam, $biologySecond);

    $count = app(CopySubjectMergesToExamAction::class)->handle($exam);

    expect($count)->toBe(1)
        ->and($exam->subjectMerges()->sole()->name)->toBe('Biology');
});

it('counts merged papers of the extra optional subject as the additional subject', function () {
    [$class, $science] = makeCollegePapersClass();
    [$bangla] = makeCollegePapersMainSubject($class, null, 'Bangla', SubjectType::Compulsory);
    [$biology] = makeCollegePapersMainSubject($class, $science, 'Biology', SubjectType::Optional);
    [$higherMath, $higherMathFirst, $higherMathSecond] = makeCollegePapersMainSubject($class, $science, 'Higher Math', SubjectType::Optional);
    $exam = makeCollegePapersExam($class);

    foreach ([$bangla, $biology, $higherMathFirst, $higherMathSecond] as $subject) {
        addCollegePapersConfig($exam, $subject);
    }

    $student = makeCollegePapersStudent($class, $science);
    chooseCollegePapersOptional($student, $biology, OptionalSubjectRole::MainOptional);
    chooseCollegePapersOptional($student, $higherMath, OptionalSubjectRole::ExtraOptional);

    $marks = [$bangla->id => 80, $biology->id => 80, $higherMathFirst->id => 10, $higherMathSecond->id => 10];

    foreach ($marks as $subjectId => $writtenMarks) {
        StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subjectId, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => $writtenMarks]);
    }

    app(CalculateExamRankings::class)->execute($exam);

    $ranking = StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $student->id)->sole();

    // The failed Higher Math (both papers, merged) is the 4th subject: no fail, no GPA loss.
    expect($ranking->gpa)->toBe(5.0)
        ->and($ranking->total_marks)->toBe(160.0);
});

it('does not let an exam take a main subject together with its own paper', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    [$class, $science] = makeCollegePapersClass();
    [$biology, $biologyFirst] = makeCollegePapersMainSubject($class, $science, 'Biology', SubjectType::Optional);
    $exam = makeCollegePapersExam($class);
    addCollegePapersConfig($exam, $biology);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'subject_id' => $biologyFirst->id,
            'written_applicable' => true,
            'written_total' => 100,
            'total_marks' => 100,
            'pass_mark' => 33,
        ])
        ->assertHasFormErrors(['subject_id']);

    expect(ExamSubjectConfig::where('exam_id', $exam->id)->count())->toBe(1)
        ->and(ExamSubjectMerge::count())->toBe(0);
});

it('does not bring back a paper merge the admin deleted when other subjects change', function () {
    [$class, $science] = makeCollegePapersClass();
    [, $biologyFirst, $biologySecond] = makeCollegePapersMainSubject($class, $science, 'Biology', SubjectType::Optional);
    [$physics] = makeCollegePapersMainSubject($class, $science, 'Physics', SubjectType::Compulsory);
    $exam = makeCollegePapersExam($class);
    addCollegePapersConfig($exam, $biologyFirst);
    $secondConfig = addCollegePapersConfig($exam, $biologySecond);

    $exam->subjectMerges()->sole()->delete();

    addCollegePapersConfig($exam, $physics);

    expect($exam->subjectMerges()->count())->toBe(0);

    // Taking the paper out and adding it back merges the pair again.
    $secondConfig->delete();
    $secondConfig->restore();

    expect($exam->subjectMerges()->sole()->name)->toBe('Biology');
});
