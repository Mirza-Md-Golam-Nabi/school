<?php

use App\Actions\ResolveSubjectOutcome;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Pages\EnterStudentMarks as AdminEnterStudentMarks;
use App\Filament\Teacher\Pages\EnterStudentMarks as TeacherEnterStudentMarks;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\GradeScale;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{class: Classes, exam: Exam, subject: Subject, config: ExamSubjectConfig, student: StudentProfile}
 */
function setUpPartAbsenceScenario(bool $hasPractical = true): array
{
    $class = Classes::create(['name' => 'Class Nine', 'order' => 1, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Annual', 'is_active' => true]);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);

    $subject = Subject::create(['name' => 'Chemistry', 'has_mcq' => true, 'has_written' => true, 'has_practical' => $hasPractical, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $config = ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'mcq_total' => 25,
        'written_total' => 50,
        'practical_total' => $hasPractical ? 25 : null,
        'total_marks' => $hasPractical ? 100 : 75,
        'pass_mark' => 33,
    ]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    return compact('class', 'exam', 'subject', 'config', 'student');
}

function actAsPartAbsenceAdmin(): void
{
    Filament::setCurrentPanel('admin');
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
}

function partAbsenceResult(Exam $exam, Subject $subject, StudentProfile $student): StudentResult
{
    return StudentResult::where(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'student_id' => $student->id])->firstOrFail();
}

it('saves practical absence while keeping the theory marks', function () {
    actAsPartAbsenceAdmin();
    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpPartAbsenceScenario();

    Livewire::test(AdminEnterStudentMarks::class, ['examId' => $exam->id, 'subjectId' => $subject->id, 'classId' => $class->id])
        ->assertSee('Practical Absent')
        ->set("marks.{$student->id}.mcq_marks", 20)
        ->set("marks.{$student->id}.written_marks", 40)
        ->set("marks.{$student->id}.practical_marks", 15)
        ->set("marks.{$student->id}.is_practical_absent", true)
        ->call('save')
        ->assertHasNoErrors();

    expect(partAbsenceResult($exam, $subject, $student))
        ->is_absent->toBeFalse()
        ->is_theory_absent->toBeFalse()
        ->is_practical_absent->toBeTrue()
        ->mcq_marks->toEqual(20)
        ->written_marks->toEqual(40)
        ->practical_marks->toBeNull()
        ->total_marks->toEqual(60);
});

it('saves theory absence while keeping the practical marks', function () {
    actAsPartAbsenceAdmin();
    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpPartAbsenceScenario();

    Livewire::test(AdminEnterStudentMarks::class, ['examId' => $exam->id, 'subjectId' => $subject->id, 'classId' => $class->id])
        ->set("marks.{$student->id}.mcq_marks", 20)
        ->set("marks.{$student->id}.practical_marks", 22)
        ->set("marks.{$student->id}.is_theory_absent", true)
        ->call('save')
        ->assertHasNoErrors();

    expect(partAbsenceResult($exam, $subject, $student))
        ->is_absent->toBeFalse()
        ->is_theory_absent->toBeTrue()
        ->mcq_marks->toBeNull()
        ->written_marks->toBeNull()
        ->practical_marks->toEqual(22)
        ->total_marks->toEqual(22);
});

it('treats absence in both parts as absent in the whole subject', function () {
    actAsPartAbsenceAdmin();
    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpPartAbsenceScenario();

    Livewire::test(AdminEnterStudentMarks::class, ['examId' => $exam->id, 'subjectId' => $subject->id, 'classId' => $class->id])
        ->set("marks.{$student->id}.is_theory_absent", true)
        ->set("marks.{$student->id}.is_practical_absent", true)
        ->call('save')
        ->assertHasNoErrors();

    expect(partAbsenceResult($exam, $subject, $student))
        ->is_absent->toBeTrue()
        ->total_marks->toEqual(0);
});

it('does not validate the marks of an absent part', function () {
    actAsPartAbsenceAdmin();
    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpPartAbsenceScenario();

    Livewire::test(AdminEnterStudentMarks::class, ['examId' => $exam->id, 'subjectId' => $subject->id, 'classId' => $class->id])
        ->set("marks.{$student->id}.mcq_marks", 20)
        ->set("marks.{$student->id}.written_marks", 40)
        ->set("marks.{$student->id}.practical_marks", 999)
        ->set("marks.{$student->id}.is_practical_absent", true)
        ->call('save')
        ->assertHasNoErrors();
});

it('keeps the single absent checkbox for a subject without a practical exam', function () {
    actAsPartAbsenceAdmin();
    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpPartAbsenceScenario(hasPractical: false);

    Livewire::test(AdminEnterStudentMarks::class, ['examId' => $exam->id, 'subjectId' => $subject->id, 'classId' => $class->id])
        ->assertDontSee('Practical Absent')
        ->set("marks.{$student->id}.is_absent", true)
        ->call('save')
        ->assertHasNoErrors();

    expect(partAbsenceResult($exam, $subject, $student))
        ->is_absent->toBeTrue()
        ->is_theory_absent->toBeTrue()
        ->is_practical_absent->toBeTrue();
});

it('lets the assigned teacher save part absence', function () {
    Filament::setCurrentPanel('teacher');
    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpPartAbsenceScenario();

    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
    TeacherSubject::create(['teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'session_year' => $exam->session_year]);
    test()->actingAs($teacher->user);

    Livewire::test(TeacherEnterStudentMarks::class, ['examId' => $exam->id, 'subjectId' => $subject->id, 'classId' => $class->id])
        ->set("marks.{$student->id}.mcq_marks", 20)
        ->set("marks.{$student->id}.written_marks", 40)
        ->set("marks.{$student->id}.is_practical_absent", true)
        ->call('save')
        ->assertHasNoErrors();

    expect(partAbsenceResult($exam, $subject, $student))
        ->is_practical_absent->toBeTrue()
        ->total_marks->toEqual(60);
});

it('fails the subject when the student missed only the practical exam', function () {
    ['exam' => $exam, 'subject' => $subject, 'config' => $config, 'student' => $student] = setUpPartAbsenceScenario();

    $result = StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $exam->class_id,
        'student_id' => $student->id,
        'mcq_marks' => 24,
        'written_marks' => 48,
        'is_practical_absent' => true,
    ]);

    $outcome = app(ResolveSubjectOutcome::class)->execute($result, $config, GradeScale::cached());

    expect($config->isPassedBy($result))->toBeFalse()
        ->and($outcome['is_fail'])->toBeTrue()
        ->and($outcome['percentage'])->toEqual(72.0);
});

it('clears both parts when the whole-subject absence is turned off', function () {
    ['exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpPartAbsenceScenario();

    $result = StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $exam->class_id,
        'student_id' => $student->id,
        'is_absent' => true,
    ]);

    expect($result->is_theory_absent)->toBeTrue()->and($result->is_practical_absent)->toBeTrue();

    $result->update(['is_absent' => false, 'mcq_marks' => 20]);

    expect($result->fresh())
        ->is_absent->toBeFalse()
        ->is_theory_absent->toBeFalse()
        ->is_practical_absent->toBeFalse()
        ->mcq_marks->toEqual(20);
});
