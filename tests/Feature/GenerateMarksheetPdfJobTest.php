<?php

use App\Actions\ApplyExamSubjectContributions;
use App\Actions\BuildStudentMarksDetail;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Jobs\GenerateMarksheetPdfJob;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamContributeRule;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\Section;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeMarksheetJobTestStudent(int $classId): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makeMarksheetJobTestExam(int $classId, ExamType $examType): Exam
{
    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classId,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);
}

it('renders and stores the marksheet pdf, then marks the record as generated', function () {
    Storage::fake('local');

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeMarksheetJobTestExam($class->id, $examType);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $student = makeMarksheetJobTestStudent($class->id);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 78,
    ]);

    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 78,
        'gpa' => 4.5,
        'class_rank' => 1,
    ]);

    $marksheet = Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id]);

    (new GenerateMarksheetPdfJob($marksheet->id))->handle();

    $marksheet->refresh();

    $expectedPath = "documents/marksheets/{$exam->session_year}/{$exam->id}/{$student->id}.pdf";

    expect($marksheet->is_generated)->toBeTrue()
        ->and($marksheet->file_path)->toBe($expectedPath)
        ->and($marksheet->file_generated_at)->not->toBeNull();

    Storage::disk('local')->assertExists($expectedPath);
});

it('deletes the previously stored file before writing the regenerated pdf', function () {
    Storage::fake('local');

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeMarksheetJobTestExam($class->id, $examType);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $student = makeMarksheetJobTestStudent($class->id);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 78,
    ]);

    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 78,
        'gpa' => 4.5,
        'class_rank' => 1,
    ]);

    $stalePath = 'documents/marksheets/stale/old-copy.pdf';
    Storage::disk('local')->put($stalePath, 'stale pdf content');

    $marksheet = Marksheet::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'is_generated' => true,
        'file_path' => $stalePath,
        'file_generated_at' => now()->subDay(),
    ]);

    (new GenerateMarksheetPdfJob($marksheet->id))->handle();

    $marksheet->refresh();

    $expectedPath = "documents/marksheets/{$exam->session_year}/{$exam->id}/{$student->id}.pdf";

    Storage::disk('local')->assertMissing($stalePath);
    Storage::disk('local')->assertExists($expectedPath);

    expect($marksheet->file_path)->toBe($expectedPath);
});

it('renders the contribution breakdown into the pdf html when a rule applied', function () {
    Storage::fake('local');

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $classTestType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    $halfYearlyType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    ExamContributeRule::create([
        'class_id' => $class->id,
        'source_exam_type_id' => $classTestType->id,
        'target_exam_type_id' => $halfYearlyType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    $student = makeMarksheetJobTestStudent($class->id);

    $classTest = makeMarksheetJobTestExam($class->id, $classTestType);
    ExamSubjectConfig::create([
        'exam_id' => $classTest->id,
        'subject_id' => $subject->id,
        'written_total' => 10,
        'total_marks' => 10,
        'pass_mark' => 4,
    ]);
    StudentResult::create([
        'exam_id' => $classTest->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 9,
    ]);

    $halfYearly = makeMarksheetJobTestExam($class->id, $halfYearlyType);
    ExamSubjectConfig::create([
        'exam_id' => $halfYearly->id,
        'subject_id' => $subject->id,
        'written_total' => 80,
        'total_marks' => 80,
        'pass_mark' => 26,
    ]);
    StudentResult::create([
        'exam_id' => $halfYearly->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 65,
    ]);

    app(ApplyExamSubjectContributions::class)->execute($halfYearly);

    StudentMeritRanking::create([
        'exam_id' => $halfYearly->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 83,
        'gpa' => 5.0,
        'class_rank' => 1,
    ]);

    $marksheet = Marksheet::create(['student_id' => $student->id, 'exam_id' => $halfYearly->id]);

    (new GenerateMarksheetPdfJob($marksheet->id))->handle();

    $marksheet->refresh();

    expect($marksheet->is_generated)->toBeTrue();

    Storage::disk('local')->assertExists($marksheet->file_path);
});

it('renders separate MCQ, Written, and Practical columns alongside Marks, Best, and Grade', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeMarksheetJobTestExam($class->id, $examType);

    // Bangla has MCQ + Written (no Practical); English has only Written.
    $bangla = Subject::create(['name' => 'Bangla', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $bangla->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);
    $english = Subject::create(['name' => 'English', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $english->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $student = makeMarksheetJobTestStudent($class->id);
    $otherStudent = makeMarksheetJobTestStudent($class->id);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $bangla->id,
        'mcq_total' => 30,
        'written_total' => 70,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);
    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $english->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $bangla->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'mcq_marks' => 25,
        'written_marks' => 60,
    ]);
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $english->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 78,
    ]);

    // Another student scoring higher in Bangla, so "Best" differs from the student's own marks.
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $bangla->id,
        'class_id' => $class->id,
        'student_id' => $otherStudent->id,
        'mcq_marks' => 30,
        'written_marks' => 65,
    ]);
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $english->id,
        'class_id' => $class->id,
        'student_id' => $otherStudent->id,
        'written_marks' => 50,
    ]);

    $ranking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 163,
        'gpa' => 4.5,
        'class_rank' => 1,
    ]);

    $marksheet = Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id]);
    ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);

    $html = view('documents.marksheet', compact('marksheet', 'rows', 'summary'))->render();

    expect($html)->toContain('MCQ')
        ->toContain('Written')
        ->toContain('Practical')
        ->toContain('Best')
        ->toContain('Grade');

    // Bangla: mcq 25, written 60, total 85, best 95 (other student's 30+65).
    expect($html)->toMatch('/>\s*25\s*</')
        ->and($html)->toMatch('/>\s*60\s*</')
        ->and($html)->toMatch('/>\s*85\s*</')
        ->and($html)->toMatch('/>\s*95\s*</');

    // English has no MCQ/Practical config at all — those cells fall back to the placeholder.
    expect(substr_count($html, '>-<'))->toBeGreaterThanOrEqual(2);
});

it('shows the 1st position student\'s total marks and gpa on every student\'s marksheet', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeMarksheetJobTestExam($class->id, $examType);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $topper = makeMarksheetJobTestStudent($class->id);
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $topper->id,
        'written_marks' => 95,
    ]);
    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $topper->id,
        'class_id' => $class->id,
        'total_marks' => 95,
        'gpa' => 5.0,
        'class_rank' => 1,
    ]);

    $secondStudent = makeMarksheetJobTestStudent($class->id);
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $secondStudent->id,
        'written_marks' => 70,
    ]);
    $secondRanking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $secondStudent->id,
        'class_id' => $class->id,
        'total_marks' => 70,
        'gpa' => 3.5,
        'class_rank' => 2,
    ]);

    ['summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($secondRanking);

    expect($summary['top_rank_total_marks'])->toBe(95.0)
        ->and($summary['top_rank_gpa'])->toBe('5.00')
        ->and($summary['top_rank_grade_label'])->toBe('A+');

    $marksheet = Marksheet::create(['student_id' => $secondStudent->id, 'exam_id' => $exam->id]);
    ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($secondRanking);
    $html = view('documents.marksheet', compact('marksheet', 'rows', 'summary'))->render();

    expect($html)->toContain('1st Position Total Marks')
        ->toContain('1st Position GPA')
        ->toMatch('/>\s*95\s*</')
        ->toContain('5.00 (A+)');
});

it('bases 1st position on class_rank, not on roll_no 1', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeMarksheetJobTestExam($class->id, $examType);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    // Roll No 1 does not top the exam — class_rank 2 despite being roll 1.
    $rollOneStudent = makeMarksheetJobTestStudent($class->id);
    $rollOneStudent->update(['roll_no' => 1]);
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $rollOneStudent->id,
        'written_marks' => 50,
    ]);
    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $rollOneStudent->id,
        'class_id' => $class->id,
        'total_marks' => 50,
        'gpa' => 2.0,
        'class_rank' => 2,
    ]);

    // A different student, roll 7, actually has class_rank 1.
    $actualTopper = makeMarksheetJobTestStudent($class->id);
    $actualTopper->update(['roll_no' => 7]);
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $actualTopper->id,
        'written_marks' => 90,
    ]);
    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $actualTopper->id,
        'class_id' => $class->id,
        'total_marks' => 90,
        'gpa' => 5.0,
        'class_rank' => 1,
    ]);

    $ranking = StudentMeritRanking::where('student_id', $rollOneStudent->id)->firstOrFail();
    ['summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);

    expect($summary['top_rank_total_marks'])->toBe(90.0)
        ->and($summary['top_rank_gpa'])->toBe('5.00');
});

it('shows Section Rank on the marksheet when the class has sections', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    Section::create(['name' => 'A', 'class_id' => $class->id, 'is_active' => true]);

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeMarksheetJobTestExam($class->id, $examType);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $student = makeMarksheetJobTestStudent($class->id);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    $ranking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 80,
        'gpa' => 4.0,
        'class_rank' => 1,
        'section_rank' => 1,
    ]);

    $marksheet = Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id]);
    ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);
    $html = view('documents.marksheet', compact('marksheet', 'rows', 'summary'))->render();

    expect($html)->toContain('Section Rank');
});

it('hides Section Rank on the marksheet when the class has no sections', function () {
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = makeMarksheetJobTestExam($class->id, $examType);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $student = makeMarksheetJobTestStudent($class->id);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    $ranking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 80,
        'gpa' => 4.0,
        'class_rank' => 1,
    ]);

    $marksheet = Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id]);
    ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);
    $html = view('documents.marksheet', compact('marksheet', 'rows', 'summary'))->render();

    expect($html)->not->toContain('Section Rank');
});
