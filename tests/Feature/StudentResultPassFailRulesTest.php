<?php

use App\Actions\CalculateExamRankings;
use App\Enums\Gender;
use App\Enums\OptionalSubjectRole;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Group;
use App\Models\StudentMeritRanking;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Verifies the school's overall result rule end to end via CalculateExamRankings:
 *
 * - Class 3-8 style curriculum: every subject is compulsory, so the student
 *   must pass every subject to pass overall.
 * - Class 9-10 style curriculum: subjects can be compulsory, main_optional, or
 *   extra_optional (per student choice). Failing a compulsory or main_optional
 *   subject fails the student overall. Failing an extra_optional subject has
 *   no effect on the overall result — no fail mark, excluded from GPA.
 */
function makeResultRulesTestCompulsoryOnlyClass(): Classes
{
    return Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
}

function makeResultRulesTestOptionalClassAndGroup(): array
{
    $class = Classes::create(['name' => 'Class Nine', 'order' => 9, 'has_group' => true, 'is_active' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $class->groups()->attach($group->id);

    return [$class, $group];
}

function makeResultRulesTestSubject(Classes $class, string $name, SubjectType $type, ?Group $group = null): Subject
{
    $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);

    $subject->classes()->attach($class->id, [
        'group_id' => $type === SubjectType::Optional ? $group?->id : null,
        'subject_type' => $type->value,
    ]);

    return $subject;
}

function makeResultRulesTestStudent(Classes $class, ?Group $group = null): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 999),
        'current_class_id' => $class->id,
        'current_group_id' => $group?->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makeResultRulesTestExam(Classes $class): Exam
{
    $examType = ExamType::create(['name' => 'Annual', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);
}

function makeResultRulesTestSubjectConfig(Exam $exam, Subject $subject): void
{
    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);
}

function resultRulesTestRankingFor(Exam $exam, StudentProfile $student): StudentMeritRanking
{
    return StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
}

// --- Class 3-8 style: all subjects compulsory, must pass every one ---

it('passes the student overall when every compulsory subject passes (class 3-8 style curriculum)', function () {
    $class = makeResultRulesTestCompulsoryOnlyClass();
    $exam = makeResultRulesTestExam($class);

    $bangla = makeResultRulesTestSubject($class, 'Bangla', SubjectType::Compulsory);
    $english = makeResultRulesTestSubject($class, 'English', SubjectType::Compulsory);
    $math = makeResultRulesTestSubject($class, 'Math', SubjectType::Compulsory);

    foreach ([$bangla, $english, $math] as $subject) {
        makeResultRulesTestSubjectConfig($exam, $subject);
    }

    $student = makeResultRulesTestStudent($class);

    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 60]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $english->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 55]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $math->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 70]);

    app(CalculateExamRankings::class)->execute($exam);

    $ranking = resultRulesTestRankingFor($exam, $student);

    expect($ranking->gpa)->toBeGreaterThan(0.0)
        ->and($ranking->total_marks)->toBe(185.0);
});

it('fails the student overall when any single compulsory subject fails (class 3-8 style curriculum)', function () {
    $class = makeResultRulesTestCompulsoryOnlyClass();
    $exam = makeResultRulesTestExam($class);

    $bangla = makeResultRulesTestSubject($class, 'Bangla', SubjectType::Compulsory);
    $english = makeResultRulesTestSubject($class, 'English', SubjectType::Compulsory);
    $math = makeResultRulesTestSubject($class, 'Math', SubjectType::Compulsory);

    foreach ([$bangla, $english, $math] as $subject) {
        makeResultRulesTestSubjectConfig($exam, $subject);
    }

    $student = makeResultRulesTestStudent($class);

    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $english->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    // Fails Math (20% < 33% pass mark) — one compulsory failure must fail the whole result.
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $math->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 20]);

    app(CalculateExamRankings::class)->execute($exam);

    $ranking = resultRulesTestRankingFor($exam, $student);

    expect($ranking->gpa)->toBe(0.0);
});

// --- Class 9-10 style: compulsory + main_optional + extra_optional ---

it('fails the student overall when a compulsory subject fails, even with passing optional subjects (class 9-10 style curriculum)', function () {
    [$class, $group] = makeResultRulesTestOptionalClassAndGroup();
    $exam = makeResultRulesTestExam($class);

    $bangla = makeResultRulesTestSubject($class, 'Bangla', SubjectType::Compulsory);
    $biology = makeResultRulesTestSubject($class, 'Biology', SubjectType::Optional, $group);
    $higherMath = makeResultRulesTestSubject($class, 'Higher Math', SubjectType::Optional, $group);

    foreach ([$bangla, $biology, $higherMath] as $subject) {
        makeResultRulesTestSubjectConfig($exam, $subject);
    }

    $student = makeResultRulesTestStudent($class, $group);

    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $biology->id, 'role' => OptionalSubjectRole::MainOptional]);
    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $higherMath->id, 'role' => OptionalSubjectRole::ExtraOptional]);

    // Fails the compulsory subject despite passing both optional subjects.
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 20]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $biology->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $higherMath->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);

    app(CalculateExamRankings::class)->execute($exam);

    $ranking = resultRulesTestRankingFor($exam, $student);

    expect($ranking->gpa)->toBe(0.0);
});

it('passes the student overall when only the extra_optional subject fails (class 9-10 style curriculum)', function () {
    [$class, $group] = makeResultRulesTestOptionalClassAndGroup();
    $exam = makeResultRulesTestExam($class);

    $bangla = makeResultRulesTestSubject($class, 'Bangla', SubjectType::Compulsory);
    $biology = makeResultRulesTestSubject($class, 'Biology', SubjectType::Optional, $group);
    $higherMath = makeResultRulesTestSubject($class, 'Higher Math', SubjectType::Optional, $group);

    foreach ([$bangla, $biology, $higherMath] as $subject) {
        makeResultRulesTestSubjectConfig($exam, $subject);
    }

    $student = makeResultRulesTestStudent($class, $group);

    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $biology->id, 'role' => OptionalSubjectRole::MainOptional]);
    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $higherMath->id, 'role' => OptionalSubjectRole::ExtraOptional]);

    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $biology->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    // Fails the extra_optional subject — must not cause an overall fail or a fail mark.
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $higherMath->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 10]);

    app(CalculateExamRankings::class)->execute($exam);

    $ranking = resultRulesTestRankingFor($exam, $student);

    expect($ranking->gpa)->toBeGreaterThan(0.0);
});

it('fails the student overall when the main_optional subject fails, even with a passing extra_optional (class 9-10 style curriculum)', function () {
    [$class, $group] = makeResultRulesTestOptionalClassAndGroup();
    $exam = makeResultRulesTestExam($class);

    $bangla = makeResultRulesTestSubject($class, 'Bangla', SubjectType::Compulsory);
    $biology = makeResultRulesTestSubject($class, 'Biology', SubjectType::Optional, $group);
    $higherMath = makeResultRulesTestSubject($class, 'Higher Math', SubjectType::Optional, $group);

    foreach ([$bangla, $biology, $higherMath] as $subject) {
        makeResultRulesTestSubjectConfig($exam, $subject);
    }

    $student = makeResultRulesTestStudent($class, $group);

    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $biology->id, 'role' => OptionalSubjectRole::MainOptional]);
    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $higherMath->id, 'role' => OptionalSubjectRole::ExtraOptional]);

    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    // Fails the main_optional subject — must fail the whole result, just like a compulsory failure.
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $biology->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 10]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $higherMath->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);

    app(CalculateExamRankings::class)->execute($exam);

    $ranking = resultRulesTestRankingFor($exam, $student);

    expect($ranking->gpa)->toBe(0.0);
});
