<?php

use App\Actions\BuildStudentMarksDetail;
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
 * The marksheet PDF and the student result view both render subject rows
 * built by BuildStudentMarksDetail. A student's extra_optional subject must
 * show "(Additional)" next to its name there, while compulsory and
 * main_optional subjects must not.
 */
it('appends "(Additional)" to the subject name only for the student\'s extra_optional subject', function () {
    $class = Classes::create(['name' => 'Class Ten', 'order' => 10, 'has_group' => true, 'is_active' => true]);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $class->groups()->attach($group->id);

    $examType = ExamType::create(['name' => 'Annual', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);

    $bangla = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $bangla->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $biology = Subject::create(['name' => 'Biology', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $biology->classes()->attach($class->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);

    $higherMath = Subject::create(['name' => 'Higher Math', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $higherMath->classes()->attach($class->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);

    foreach ([$bangla, $biology, $higherMath] as $subject) {
        ExamSubjectConfig::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'written_total' => 100,
            'total_marks' => 100,
            'pass_mark' => 33,
        ]);
    }

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 999),
        'current_class_id' => $class->id,
        'current_group_id' => $group->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $biology->id, 'role' => OptionalSubjectRole::MainOptional]);
    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $higherMath->id, 'role' => OptionalSubjectRole::ExtraOptional]);

    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $bangla->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $biology->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $higherMath->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);

    $ranking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 240,
        'gpa' => 5.0,
        'class_rank' => 1,
    ]);

    ['rows' => $rows] = app(BuildStudentMarksDetail::class)->handle($ranking);

    $subjectNames = $rows->pluck('subject_name', 'subject_name')->keys();

    expect($subjectNames)->toContain('Bangla')
        ->and($subjectNames)->toContain('Biology')
        ->and($subjectNames)->toContain('Higher Math (Additional)')
        ->and($subjectNames)->not->toContain('Higher Math')
        ->and($subjectNames)->not->toContain('Bangla (Additional)')
        ->and($subjectNames)->not->toContain('Biology (Additional)');
});
