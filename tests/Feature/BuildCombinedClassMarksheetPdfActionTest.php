<?php

use App\Actions\BuildCombinedClassMarksheetPdfAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

function makeCombinedMarksheetTestStudent(int $classId, int $rollNo): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function seedCombinedMarksheetTestResult(Exam $exam, Classes $class, Subject $subject, StudentProfile $student, float $marks, ?int $classRank = null): void
{
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => $marks,
    ]);

    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => $marks,
        'gpa' => 4.5,
        'class_rank' => $classRank ?? 1,
    ]);
}

it('combines only already-generated marksheets for the given class and exam, one page per student', function () {
    $class = Classes::create(['name' => 'Combined Class', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $generatedOne = makeCombinedMarksheetTestStudent($class->id, rollNo: 1);
    seedCombinedMarksheetTestResult($exam, $class, $subject, $generatedOne, 80, classRank: 1);
    Marksheet::create(['student_id' => $generatedOne->id, 'exam_id' => $exam->id, 'is_generated' => true]);

    $generatedTwo = makeCombinedMarksheetTestStudent($class->id, rollNo: 2);
    seedCombinedMarksheetTestResult($exam, $class, $subject, $generatedTwo, 70, classRank: 2);
    Marksheet::create(['student_id' => $generatedTwo->id, 'exam_id' => $exam->id, 'is_generated' => true]);

    // Has a ranking but the marksheet was never generated — must be excluded.
    $notGenerated = makeCombinedMarksheetTestStudent($class->id, rollNo: 3);
    seedCombinedMarksheetTestResult($exam, $class, $subject, $notGenerated, 60, classRank: 3);
    Marksheet::create(['student_id' => $notGenerated->id, 'exam_id' => $exam->id, 'is_generated' => false]);

    $pdf = app(BuildCombinedClassMarksheetPdfAction::class)->handle($class, $exam);

    expect($pdf)->toStartWith('%PDF');

    $pageCount = preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    expect($pageCount)->toBe(2);
});

it('excludes generated marksheets belonging to a different class', function () {
    $class = Classes::create(['name' => 'Combined Class A', 'order' => 1]);
    $otherClass = Classes::create(['name' => 'Combined Class B', 'order' => 2]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $inClass = makeCombinedMarksheetTestStudent($class->id, rollNo: 1);
    seedCombinedMarksheetTestResult($exam, $class, $subject, $inClass, 80, classRank: 1);
    Marksheet::create(['student_id' => $inClass->id, 'exam_id' => $exam->id, 'is_generated' => true]);

    // Same exam id would be unusual for another class, but simulate a stray marksheet row
    // referencing a student who is not actually in this class anymore.
    $otherStudent = makeCombinedMarksheetTestStudent($otherClass->id, rollNo: 1);
    Marksheet::create(['student_id' => $otherStudent->id, 'exam_id' => $exam->id, 'is_generated' => true]);

    $pdf = app(BuildCombinedClassMarksheetPdfAction::class)->handle($class, $exam);

    $pageCount = preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    expect($pageCount)->toBe(1);
});

it('aborts with 404 when no marksheet has been generated yet for the class and exam', function () {
    $class = Classes::create(['name' => 'Combined Class Empty', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);

    expect(fn () => app(BuildCombinedClassMarksheetPdfAction::class)->handle($class, $exam))
        ->toThrow(NotFoundHttpException::class);
});

it('streams the combined pdf as a download through the http route', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Combined Class Route', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $student = makeCombinedMarksheetTestStudent($class->id, rollNo: 1);
    seedCombinedMarksheetTestResult($exam, $class, $subject, $student, 80, classRank: 1);
    Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id, 'is_generated' => true]);

    $response = $this->actingAs($admin)->get(route('marksheets.class.download', ['class' => $class->id, 'exam' => $exam->id]));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');
});
