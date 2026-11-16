<?php

use App\Actions\BuildCombinedClassAdmitCardsPdfAction;
use App\Actions\GenerateAdmitCardsForExamAction;
use App\Actions\GenerateAdmitCardsForExamTypeAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Jobs\GenerateAdmitCardBundlePdfJob;
use App\Models\AdmitCard;
use App\Models\AdmitCardBundle;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeAdmitCardBundleTestExam(?Classes $class = null, string $examTypeName = 'Half Yearly'): Exam
{
    $class ??= Classes::create(['name' => 'Bundle Class', 'order' => 1]);
    $examType = ExamType::create(['name' => $examTypeName, 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);
}

function makeAdmitCardBundleTestCard(Exam $exam, int $rollNo, bool $isGenerated = false): AdmitCard
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $exam->class_id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    return AdmitCard::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'is_generated' => $isGenerated,
        'page_size' => 'A4',
    ]);
}

it('builds and stores the bundle pdf, then marks it ready', function () {
    Storage::fake('local');

    $exam = makeAdmitCardBundleTestExam();
    makeAdmitCardBundleTestCard($exam, 1);
    makeAdmitCardBundleTestCard($exam, 2);

    $bundle = AdmitCardBundle::create(['exam_id' => $exam->id, 'generation_token' => 'token-1']);

    (new GenerateAdmitCardBundlePdfJob($bundle->id, 'token-1'))->handle(app(BuildCombinedClassAdmitCardsPdfAction::class));

    $bundle->refresh();

    expect($bundle->is_generated)->toBeTrue()
        ->and($bundle->card_count)->toBe(2)
        ->and($bundle->file_path)->toBe("documents/admit_cards/{$exam->session_year}/{$exam->id}/bundle.pdf")
        ->and($bundle->isReadyFor(2))->toBeTrue()
        ->and($bundle->isReadyFor(1))->toBeFalse();

    expect(Storage::disk('local')->get($bundle->file_path))->toStartWith('%PDF');
});

it('drops its result when a newer generation has started meanwhile', function () {
    Storage::fake('local');

    $exam = makeAdmitCardBundleTestExam();
    makeAdmitCardBundleTestCard($exam, 1);

    $bundle = AdmitCardBundle::create(['exam_id' => $exam->id, 'generation_token' => 'newer-token']);

    (new GenerateAdmitCardBundlePdfJob($bundle->id, 'older-token'))->handle(app(BuildCombinedClassAdmitCardsPdfAction::class));

    expect($bundle->refresh()->is_generated)->toBeFalse()
        ->and($bundle->file_path)->toBeNull();
});

it('queues the bundle alongside the per-card jobs when admit cards are generated', function () {
    Queue::fake();

    $exam = makeAdmitCardBundleTestExam();
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'current_class_id' => $exam->class_id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    app(GenerateAdmitCardsForExamAction::class)->handle($exam);

    $bundle = AdmitCardBundle::where('exam_id', $exam->id)->sole();

    expect($bundle->is_generated)->toBeFalse();

    Queue::assertPushed(GenerateAdmitCardBundlePdfJob::class, fn (GenerateAdmitCardBundlePdfJob $job): bool => $job->admitCardBundleId === $bundle->id && $job->generationToken === $bundle->generation_token);
});

it('does not rebuild a ready bundle when a re-run changes nothing', function () {
    Queue::fake();
    Storage::fake('local');

    $exam = makeAdmitCardBundleTestExam();
    makeAdmitCardBundleTestCard($exam, 1)->update(['design' => 'simple']);

    Storage::disk('local')->put('bundle.pdf', '%PDF-ready');
    AdmitCardBundle::create(['exam_id' => $exam->id, 'file_path' => 'bundle.pdf', 'card_count' => 1, 'is_generated' => true]);

    app(GenerateAdmitCardsForExamAction::class)->handle($exam);

    Queue::assertNotPushed(GenerateAdmitCardBundlePdfJob::class);
});

it('downloads the stored bundle when it still covers every admit card', function () {
    Storage::fake('local');
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $exam = makeAdmitCardBundleTestExam();
    makeAdmitCardBundleTestCard($exam, 1, isGenerated: true);

    Storage::disk('local')->put('bundle.pdf', '%PDF-stored-bundle');
    AdmitCardBundle::create(['exam_id' => $exam->id, 'file_path' => 'bundle.pdf', 'card_count' => 1, 'is_generated' => true]);

    $response = $this->actingAs($admin)->get(route('admit-cards.class.download', ['class' => $exam->class_id, 'exam' => $exam->id]));

    $response->assertOk();
    expect($response->streamedContent())->toBe('%PDF-stored-bundle');
});

it('builds the pdf on the fly when the stored bundle is stale', function () {
    Storage::fake('local');
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $exam = makeAdmitCardBundleTestExam();
    makeAdmitCardBundleTestCard($exam, 1, isGenerated: true);
    makeAdmitCardBundleTestCard($exam, 2, isGenerated: true);

    Storage::disk('local')->put('bundle.pdf', '%PDF-stored-bundle');
    AdmitCardBundle::create(['exam_id' => $exam->id, 'file_path' => 'bundle.pdf', 'card_count' => 1, 'is_generated' => true]);

    $response = $this->actingAs($admin)->get(route('admit-cards.class.download', ['class' => $exam->class_id, 'exam' => $exam->id]));

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF')
        ->not->toBe('%PDF-stored-bundle');
});

it('deletes other exam types\' bundles and their files when switching exam type', function () {
    Queue::fake();
    Storage::fake('local');

    $class = Classes::create(['name' => 'Switch Class', 'order' => 1]);
    $oldExam = makeAdmitCardBundleTestExam($class, 'Half Yearly');
    $newExam = makeAdmitCardBundleTestExam($class, 'Annual');

    Storage::disk('local')->put('old-bundle.pdf', '%PDF-old');
    AdmitCardBundle::create(['exam_id' => $oldExam->id, 'file_path' => 'old-bundle.pdf', 'card_count' => 1, 'is_generated' => true]);

    app(GenerateAdmitCardsForExamTypeAction::class)->handle($newExam->examType);

    expect(AdmitCardBundle::where('exam_id', $oldExam->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('old-bundle.pdf');
});
