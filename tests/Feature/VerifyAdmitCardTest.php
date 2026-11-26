<?php

use App\Actions\RenderAdmitCardPdfAction;
use App\Enums\AdmitCardDesign;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\SchoolSetting;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\AdmitCardSample;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeVerifiableAdmitCard(): AdmitCard
{
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    $user = User::factory()->create(['name' => 'Rahim Uddin', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 17,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    return AdmitCard::create(['student_id' => $student->id, 'exam_id' => $exam->id]);
}

it('gives every new admit card a unique, unguessable verification token', function () {
    $admitCard = makeVerifiableAdmitCard();

    expect($admitCard->verification_token)->toHaveLength(16)
        ->and($admitCard->verificationUrl())->toBe(route('admit-cards.verify', ['token' => $admitCard->verification_token]));
});

it('keeps the token when the card is regenerated in another design', function () {
    $admitCard = makeVerifiableAdmitCard();
    $token = $admitCard->verification_token;

    $admitCard->update(['design' => AdmitCardDesign::WithRoutine, 'is_generated' => false]);

    expect($admitCard->fresh()->verification_token)->toBe($token);
});

it('shows the card holder on the public verification page without login', function () {
    $admitCard = makeVerifiableAdmitCard();

    $this->get($admitCard->verificationUrl())
        ->assertOk()
        ->assertSee('Rahim Uddin')
        ->assertSee('Half Yearly')
        ->assertSee('Class One')
        ->assertSee('noindex', false);
});

it('answers not found for an unknown token', function () {
    makeVerifiableAdmitCard();

    $this->get(route('admit-cards.verify', ['token' => 'NoSuchToken12345']))
        ->assertNotFound()
        ->assertDontSee('Rahim Uddin');
});

it('prints the verification QR code on every admit card design', function (AdmitCardDesign $design) {
    $admitCard = makeVerifiableAdmitCard();
    $admitCard->update(['design' => $design]);

    $html = view($design->view(), ['admitCard' => $admitCard->fresh()])->render();

    expect($html)->toContain('<barcode')
        ->toContain('type="QR"')
        ->toContain($admitCard->verificationUrl());
})->with(AdmitCardDesign::cases());

it('leaves the QR code off when the school turns it off', function () {
    SchoolSetting::set(AdmitCard::SHOW_QR_SETTING, '0');
    $admitCard = makeVerifiableAdmitCard();

    $html = view(AdmitCardDesign::Simple->view(), ['admitCard' => $admitCard])->render();

    expect($html)->not->toContain('<barcode');
});

it('renders the admit card pdf with the QR code', function () {
    $pdf = app(RenderAdmitCardPdfAction::class)->handle(AdmitCardSample::make(AdmitCardDesign::Simple));

    expect($pdf)->toStartWith('%PDF');
});
