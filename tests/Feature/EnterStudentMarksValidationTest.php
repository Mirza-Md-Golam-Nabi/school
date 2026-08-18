<?php

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
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

function setUpMarksValidationScenario(): array
{
    $class = Classes::create(['name' => 'Class Marks', 'order' => 1, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);

    $subject = Subject::create(['name' => 'Physics', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $config = ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'mcq_total' => 10,
        'written_total' => 20,
        'total_marks' => 30,
        'pass_mark' => 12,
    ]);

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    return compact('class', 'exam', 'subject', 'config', 'student');
}

it('rejects marks that exceed the configured mcq and written totals (admin)', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpMarksValidationScenario();

    Livewire::test(AdminEnterStudentMarks::class, [
        'examId' => $exam->id,
        'subjectId' => $subject->id,
        'classId' => $class->id,
    ])
        ->set("marks.{$student->id}.mcq_marks", 12)
        ->set("marks.{$student->id}.written_marks", 140)
        ->call('save')
        ->assertHasErrors([
            "marks.{$student->id}.mcq_marks",
            "marks.{$student->id}.written_marks",
        ]);

    assertDatabaseMissing(StudentResult::class, [
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'student_id' => $student->id,
    ]);
});

it('accepts marks within the configured totals (admin)', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpMarksValidationScenario();

    Livewire::test(AdminEnterStudentMarks::class, [
        'examId' => $exam->id,
        'subjectId' => $subject->id,
        'classId' => $class->id,
    ])
        ->set("marks.{$student->id}.mcq_marks", 8)
        ->set("marks.{$student->id}.written_marks", 15)
        ->call('save')
        ->assertHasNoErrors();

    expect(StudentResult::where([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'student_id' => $student->id,
    ])->first())
        ->mcq_marks->toEqual(8)
        ->written_marks->toEqual(15);
});

it('clears the validation errors once the marks are corrected within the totals (admin)', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpMarksValidationScenario();

    $component = Livewire::test(AdminEnterStudentMarks::class, [
        'examId' => $exam->id,
        'subjectId' => $subject->id,
        'classId' => $class->id,
    ])
        ->set("marks.{$student->id}.mcq_marks", 12)
        ->set("marks.{$student->id}.written_marks", 140)
        ->call('save')
        ->assertHasErrors([
            "marks.{$student->id}.mcq_marks",
            "marks.{$student->id}.written_marks",
        ]);

    $component
        ->set("marks.{$student->id}.mcq_marks", 8)
        ->set("marks.{$student->id}.written_marks", 15)
        ->call('save')
        ->assertHasNoErrors();

    expect(StudentResult::where([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'student_id' => $student->id,
    ])->first())
        ->mcq_marks->toEqual(8)
        ->written_marks->toEqual(15);
});

it('rejects marks that exceed the configured totals (teacher)', function () {
    Filament::setCurrentPanel('teacher');

    ['class' => $class, 'exam' => $exam, 'subject' => $subject, 'student' => $student] = setUpMarksValidationScenario();

    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    TeacherSubject::create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'session_year' => $exam->session_year,
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(TeacherEnterStudentMarks::class, [
        'examId' => $exam->id,
        'subjectId' => $subject->id,
        'classId' => $class->id,
    ])
        ->set("marks.{$student->id}.mcq_marks", 12)
        ->set("marks.{$student->id}.written_marks", 140)
        ->call('save')
        ->assertHasErrors([
            "marks.{$student->id}.mcq_marks",
            "marks.{$student->id}.written_marks",
        ]);

    assertDatabaseMissing(StudentResult::class, [
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'student_id' => $student->id,
    ]);
});
