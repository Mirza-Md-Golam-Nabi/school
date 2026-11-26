<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Jobs\GenerateMarksheetPdfJob;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\SchoolSetting;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeVerifiableMarksheet(bool $isPublished = true, float $writtenMarks = 78): Marksheet
{
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => $isPublished,
    ]);

    $subject = Subject::create(['name' => 'Mathematics', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $user = User::factory()->create(['name' => 'Rahim Uddin', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 17,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

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
        'written_marks' => $writtenMarks,
    ]);

    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => $writtenMarks,
        'gpa' => 4.5,
        'class_rank' => 1,
    ]);

    return Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id]);
}

it('gives every new marksheet a short, unambiguous verification code', function () {
    $marksheet = makeVerifiableMarksheet();

    expect($marksheet->verification_code)->toMatch('/^['.Marksheet::VERIFICATION_CODE_ALPHABET.']{8}$/')
        ->and($marksheet->formattedVerificationCode())->toBe(substr($marksheet->verification_code, 0, 4).'-'.substr($marksheet->verification_code, 4))
        ->and($marksheet->verificationUrl())->toBe(route('marksheets.verify', ['code' => $marksheet->verification_code]));
});

it('keeps the code when the marksheet is regenerated', function () {
    $marksheet = makeVerifiableMarksheet();
    $code = $marksheet->verification_code;

    $marksheet->update(['is_generated' => false, 'file_path' => null]);

    expect($marksheet->fresh()->verification_code)->toBe($code);
});

it('shows the current subject-wise result on the public verification page without login', function () {
    $marksheet = makeVerifiableMarksheet();

    $this->get($marksheet->verificationUrl())
        ->assertOk()
        ->assertSee('Rahim Uddin')
        ->assertSee('Half Yearly')
        ->assertSee('Mathematics')
        ->assertSee($marksheet->formattedVerificationCode())
        ->assertSee('noindex', false);
});

it('shows the corrected marks once results are recalculated, not the printed ones', function () {
    $marksheet = makeVerifiableMarksheet(writtenMarks: 78);

    StudentResult::query()->sole()->update(['written_marks' => 64]);

    $this->get($marksheet->verificationUrl())
        ->assertOk()
        ->assertSee('৬৪')
        ->assertDontSee('৭৮');
});

it('hides the marks while the exam result is not published', function () {
    $marksheet = makeVerifiableMarksheet(isPublished: false);

    $this->get($marksheet->verificationUrl())
        ->assertOk()
        ->assertSee('Rahim Uddin')
        ->assertDontSee('Mathematics')
        ->assertDontSee('৭৮');
});

it('answers not found for an unknown code', function () {
    makeVerifiableMarksheet();

    $this->get(route('marksheets.verify', ['code' => 'ZZZZZZZZ']))
        ->assertNotFound()
        ->assertDontSee('Rahim Uddin');
});

it('opens the marksheet from a code typed in by hand, however it is written', function (callable $typed) {
    $marksheet = makeVerifiableMarksheet();

    $this->get(route('verify', ['code' => $typed($marksheet)]))
        ->assertRedirect(route('marksheets.verify', ['code' => $marksheet->verification_code]));
})->with([
    'as printed' => [fn (Marksheet $marksheet): string => $marksheet->formattedVerificationCode()],
    'lowercase' => [fn (Marksheet $marksheet): string => strtolower($marksheet->formattedVerificationCode())],
    'with spaces' => [fn (Marksheet $marksheet): string => ' '.implode(' ', str_split($marksheet->verification_code, 4)).' '],
]);

it('shows the code entry form, and says so when a typed code matches nothing', function () {
    makeVerifiableMarksheet();

    $this->get(route('verify'))
        ->assertOk()
        ->assertSee('name="code"', false);

    $this->get(route('verify', ['code' => 'ABCD-EFGH']))
        ->assertNotFound()
        ->assertSee('value="ABCD-EFGH"', false)
        ->assertDontSee('Rahim Uddin');
});

it('prints the verification QR code, short link and code on the marksheet', function () {
    $marksheet = makeVerifiableMarksheet();

    $html = view('documents.marksheet', [
        'marksheet' => $marksheet,
        'rows' => collect(),
        'summary' => makeEmptyMarksheetSummary(),
    ])->render();

    expect($html)->toContain('<barcode')
        ->toContain('type="QR"')
        ->toContain($marksheet->verificationUrl())
        ->toContain(preg_replace('#^https?://#', '', route('verify')))
        ->toContain($marksheet->formattedVerificationCode());
});

it('leaves the QR code off when the school turns it off', function () {
    SchoolSetting::set(Marksheet::SHOW_QR_SETTING, '0');
    $marksheet = makeVerifiableMarksheet();

    $html = view('documents.marksheet', [
        'marksheet' => $marksheet,
        'rows' => collect(),
        'summary' => makeEmptyMarksheetSummary(),
    ])->render();

    expect($html)->not->toContain('<barcode');
});

it('renders the marksheet pdf with the QR code', function () {
    Storage::fake('local');
    $marksheet = makeVerifiableMarksheet();

    (new GenerateMarksheetPdfJob($marksheet->id))->handle();

    expect(Storage::disk('local')->get($marksheet->fresh()->file_path))->toStartWith('%PDF');
});

/**
 * @return array<string, mixed>
 */
function makeEmptyMarksheetSummary(): array
{
    return [
        'obtained_marks' => 0,
        'obtained_full_marks' => 0,
        'gpa' => '0.00',
        'overall_grade_label' => 'F',
        'class_rank' => null,
        'section_rank' => null,
        'total_grade_point' => '0.00',
        'top_rank_obtained_marks' => null,
        'top_rank_gpa' => null,
        'top_rank_grade_label' => null,
        'working_days' => 0,
        'present_days' => 0,
    ];
}
