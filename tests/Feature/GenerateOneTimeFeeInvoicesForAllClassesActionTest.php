<?php

use App\Actions\GenerateOneTimeFeeInvoicesForAllClassesAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\StudentFeeInvoiceGeneratedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createAllClassesOneTimeInvoiceTestStudent(int $classId): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 100000),
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('generates one-time invoices for every active class with a fee structure for the fee type', function () {
    Notification::fake();

    $classOne = Classes::create(['name' => 'Class Nine', 'order' => 9, 'is_active' => true]);
    $classTwo = Classes::create(['name' => 'Class Ten', 'order' => 10, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);

    foreach ([$classOne, $classTwo] as $class) {
        FeeStructure::create([
            'class_id' => $class->id,
            'fee_type_id' => $feeType->id,
            'amount' => 1500,
            'due_day' => 10,
            'session_year' => now()->year,
            'is_active' => true,
        ]);
    }

    $studentOne = createAllClassesOneTimeInvoiceTestStudent($classOne->id);
    $studentTwo = createAllClassesOneTimeInvoiceTestStudent($classTwo->id);

    $result = app(GenerateOneTimeFeeInvoicesForAllClassesAction::class)->handle($feeType->id, now()->year);

    expect($result)->toBe(['generated' => 2, 'skipped' => 0]);

    expect(StudentFeeInvoice::where('student_id', $studentOne->id)->exists())->toBeTrue()
        ->and(StudentFeeInvoice::where('student_id', $studentTwo->id)->exists())->toBeTrue();

    Notification::assertSentTo($studentOne->user, StudentFeeInvoiceGeneratedNotification::class);
    Notification::assertSentTo($studentTwo->user, StudentFeeInvoiceGeneratedNotification::class);
});

it('does not create duplicate invoices when run twice for the same fee type/year', function () {
    $class = Classes::create(['name' => 'Class Eleven', 'order' => 11, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 1500,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    createAllClassesOneTimeInvoiceTestStudent($class->id);

    app(GenerateOneTimeFeeInvoicesForAllClassesAction::class)->handle($feeType->id, now()->year);
    $result = app(GenerateOneTimeFeeInvoicesForAllClassesAction::class)->handle($feeType->id, now()->year);

    expect($result)->toBe(['generated' => 0, 'skipped' => 1])
        ->and(StudentFeeInvoice::count())->toBe(1);
});

it('skips classes without an active fee structure for the fee type', function () {
    $classWithStructure = Classes::create(['name' => 'Class Twelve', 'order' => 12, 'is_active' => true]);
    $classWithoutStructure = Classes::create(['name' => 'Class Thirteen', 'order' => 13, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $classWithStructure->id,
        'fee_type_id' => $feeType->id,
        'amount' => 1500,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    $studentWithStructure = createAllClassesOneTimeInvoiceTestStudent($classWithStructure->id);
    $studentWithoutStructure = createAllClassesOneTimeInvoiceTestStudent($classWithoutStructure->id);

    $result = app(GenerateOneTimeFeeInvoicesForAllClassesAction::class)->handle($feeType->id, now()->year);

    expect($result)->toBe(['generated' => 1, 'skipped' => 0])
        ->and(StudentFeeInvoice::where('student_id', $studentWithStructure->id)->exists())->toBeTrue()
        ->and(StudentFeeInvoice::where('student_id', $studentWithoutStructure->id)->exists())->toBeFalse();
});

it('skips inactive classes even if they have an active fee structure', function () {
    $inactiveClass = Classes::create(['name' => 'Class Fourteen', 'order' => 14, 'is_active' => false]);
    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $inactiveClass->id,
        'fee_type_id' => $feeType->id,
        'amount' => 1500,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    createAllClassesOneTimeInvoiceTestStudent($inactiveClass->id);

    $result = app(GenerateOneTimeFeeInvoicesForAllClassesAction::class)->handle($feeType->id, now()->year);

    expect($result)->toBe(['generated' => 0, 'skipped' => 0])
        ->and(StudentFeeInvoice::count())->toBe(0);
});

it('refuses to generate for a monthly fee type', function () {
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    app(GenerateOneTimeFeeInvoicesForAllClassesAction::class)->handle($feeType->id, now()->year);
})->throws(InvalidArgumentException::class);
