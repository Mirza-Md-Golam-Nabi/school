<?php

use App\Actions\BuildCombinedClassMarksheetPdfAction;
use App\Enums\Gender;
use App\Enums\MarksheetBundleType;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Jobs\GenerateMarksheetBundlePdfJob;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\MarksheetBundle;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Class + exam with one passing and one failing student, both with a
 * ranking and a marksheet record.
 */
function seedMarksheetBundleJobTestExam(): Exam
{
    $class = Classes::create(['name' => 'Bundle Job Class', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Annual', 'is_active' => true]);
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

    foreach ([[1, 80], [2, 20]] as [$rollNo, $marks]) {
        $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
        $student = StudentProfile::create([
            'user_id' => $user->id,
            'roll_no' => $rollNo,
            'current_class_id' => $class->id,
            'session_year' => now()->year,
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

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
            'gpa' => 4.0,
            'class_rank' => $rollNo,
        ]);

        Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id, 'is_generated' => false]);
    }

    return $exam;
}

it('builds and stores the bundle pdf, then marks it ready', function () {
    Storage::fake('local');

    $exam = seedMarksheetBundleJobTestExam();

    $bundle = MarksheetBundle::create([
        'exam_id' => $exam->id,
        'type' => MarksheetBundleType::All,
        'subject_language' => 'en',
        'generation_token' => 'token-1',
    ]);

    (new GenerateMarksheetBundlePdfJob($bundle->id, 'token-1'))->handle(app(BuildCombinedClassMarksheetPdfAction::class));

    $bundle->refresh();

    expect($bundle->is_generated)->toBeTrue()
        ->and($bundle->student_count)->toBe(2)
        ->and($bundle->file_path)->toBe("documents/marksheets/{$exam->session_year}/{$exam->id}/bundle-all.pdf")
        ->and($bundle->isReadyFor('en'))->toBeTrue()
        ->and($bundle->isReadyFor('bn'))->toBeFalse();

    expect(Storage::disk('local')->get($bundle->file_path))->toStartWith('%PDF');
});

it('marks an empty bundle ready without storing a file when no student matches', function () {
    Storage::fake('local');

    $exam = seedMarksheetBundleJobTestExam();
    StudentResult::where('exam_id', $exam->id)->get()->each->update(['written_marks' => 80]);

    Storage::disk('local')->put('old-failed.pdf', '%PDF-old');

    $bundle = MarksheetBundle::create([
        'exam_id' => $exam->id,
        'type' => MarksheetBundleType::Failed,
        'subject_language' => 'en',
        'file_path' => 'old-failed.pdf',
        'generation_token' => 'token-1',
    ]);

    (new GenerateMarksheetBundlePdfJob($bundle->id, 'token-1'))->handle(app(BuildCombinedClassMarksheetPdfAction::class));

    $bundle->refresh();

    expect($bundle->is_generated)->toBeTrue()
        ->and($bundle->student_count)->toBe(0)
        ->and($bundle->file_path)->toBeNull()
        ->and($bundle->isReadyFor('en'))->toBeTrue();

    Storage::disk('local')->assertMissing('old-failed.pdf');
});

it('drops its result when a newer generation has started meanwhile', function () {
    Storage::fake('local');

    $exam = seedMarksheetBundleJobTestExam();

    $bundle = MarksheetBundle::create([
        'exam_id' => $exam->id,
        'type' => MarksheetBundleType::Passed,
        'subject_language' => 'en',
        'generation_token' => 'newer-token',
    ]);

    (new GenerateMarksheetBundlePdfJob($bundle->id, 'older-token'))->handle(app(BuildCombinedClassMarksheetPdfAction::class));

    expect($bundle->refresh()->is_generated)->toBeFalse()
        ->and($bundle->file_path)->toBeNull();
});
