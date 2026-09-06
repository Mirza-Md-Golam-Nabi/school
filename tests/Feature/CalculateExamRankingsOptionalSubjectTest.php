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
 * These tests prove that main/extra optional is resolved per student (via
 * student_optional_subjects), not per class/group — the same "optional"
 * subject in class_group_subject can be one student's main_optional (which
 * behaves like a compulsory subject for GPA/fail purposes) and another
 * student's extra_optional (bonus marks, excluded from GPA on failure).
 */
function makeOptionalSubjectTestClassAndGroup(): array
{
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'has_group' => true, 'is_active' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $class->groups()->attach($group->id);

    return [$class, $group];
}

function makeOptionalSubjectTestCompulsorySubject(Classes $class, string $name): Subject
{
    $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    return $subject;
}

function makeOptionalSubjectTestOptionalSubject(Classes $class, Group $group, string $name): Subject
{
    $subject = Subject::create(['name' => $name, 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);

    return $subject;
}

function makeOptionalSubjectTestStudent(Classes $class, Group $group): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 999),
        'current_class_id' => $class->id,
        'current_group_id' => $group->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makeOptionalSubjectTestExam(Classes $class): Exam
{
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);
}

it('excludes a failed extra_optional subject from GPA without failing the student overall', function () {
    [$class, $group] = makeOptionalSubjectTestClassAndGroup();
    $exam = makeOptionalSubjectTestExam($class);

    $bangla = makeOptionalSubjectTestCompulsorySubject($class, 'Bangla');
    $biology = makeOptionalSubjectTestOptionalSubject($class, $group, 'Biology');
    $higherMath = makeOptionalSubjectTestOptionalSubject($class, $group, 'Higher Math');

    foreach ([$bangla, $biology, $higherMath] as $subject) {
        ExamSubjectConfig::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'written_total' => 100,
            'total_marks' => 100,
            'pass_mark' => 33,
        ]);
    }

    $student = makeOptionalSubjectTestStudent($class, $group);

    // This student chose Biology as main_optional and Higher Math as extra_optional.
    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'group_id' => $group->id,
        'subject_id' => $biology->id,
        'role' => OptionalSubjectRole::MainOptional,
    ]);
    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'group_id' => $group->id,
        'subject_id' => $higherMath->id,
        'role' => OptionalSubjectRole::ExtraOptional,
    ]);

    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $biology->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    // Fails the extra_optional subject (10% < 33% pass mark).
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $higherMath->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 10]);

    app(CalculateExamRankings::class)->execute($exam);

    $ranking = StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $student->id)->first();

    // Bangla (A+, 5.0) + Biology (A+, 5.0) averaged — Higher Math's fail is excluded, not penalized.
    expect($ranking->gpa)->toBe(5.0)
        ->and($ranking->total_marks)->toBe(170.0);
});

it('fails the student overall when the failed subject is their main_optional instead', function () {
    [$class, $group] = makeOptionalSubjectTestClassAndGroup();
    $exam = makeOptionalSubjectTestExam($class);

    $bangla = makeOptionalSubjectTestCompulsorySubject($class, 'Bangla');
    $biology = makeOptionalSubjectTestOptionalSubject($class, $group, 'Biology');
    $higherMath = makeOptionalSubjectTestOptionalSubject($class, $group, 'Higher Math');

    foreach ([$bangla, $biology, $higherMath] as $subject) {
        ExamSubjectConfig::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'written_total' => 100,
            'total_marks' => 100,
            'pass_mark' => 33,
        ]);
    }

    $student = makeOptionalSubjectTestStudent($class, $group);

    // This student chose Higher Math as main_optional and Biology as extra_optional
    // — the opposite of the other test, proving the choice is per student.
    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'group_id' => $group->id,
        'subject_id' => $higherMath->id,
        'role' => OptionalSubjectRole::MainOptional,
    ]);
    StudentOptionalSubject::create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'group_id' => $group->id,
        'subject_id' => $biology->id,
        'role' => OptionalSubjectRole::ExtraOptional,
    ]);

    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    // Fails the main_optional subject this time.
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $higherMath->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 10]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $biology->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);

    app(CalculateExamRankings::class)->execute($exam);

    $ranking = StudentMeritRanking::where('exam_id', $exam->id)->where('student_id', $student->id)->first();

    expect($ranking->gpa)->toBe(0.0);
});
