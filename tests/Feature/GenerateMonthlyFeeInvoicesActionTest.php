<?php

use App\Actions\GenerateMonthlyFeeInvoicesAction;
use App\Enums\FeeDiscountType;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\FeeDiscount;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\StudentFeeDiscount;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\StudentFeeInvoiceGeneratedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createMonthlyInvoiceTestStudent(int $classId): StudentProfile
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

it('generates a monthly invoice for every active student in classes with a monthly fee structure', function () {
    Notification::fake();

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 500,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    $student1 = createMonthlyInvoiceTestStudent($class->id);
    $student2 = createMonthlyInvoiceTestStudent($class->id);

    $result = app(GenerateMonthlyFeeInvoicesAction::class)->handle(now()->month, now()->year);

    expect($result)->toBe(['generated' => 2, 'skipped' => 0]);

    expect(StudentFeeInvoice::where('student_id', $student1->id)->where('month', now()->month)->exists())->toBeTrue()
        ->and(StudentFeeInvoice::where('student_id', $student2->id)->where('month', now()->month)->exists())->toBeTrue();

    Notification::assertSentTo($student1->user, StudentFeeInvoiceGeneratedNotification::class);
    Notification::assertSentTo($student2->user, StudentFeeInvoiceGeneratedNotification::class);
});

it('sends a separate notification for each fee type generated in the same run', function () {
    Notification::fake();

    $class = Classes::create(['name' => 'Class One B', 'order' => 1]);
    $tuition = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $transport = FeeType::create(['name' => 'Transport Fee', 'is_monthly' => true, 'is_active' => true]);

    foreach ([$tuition, $transport] as $feeType) {
        FeeStructure::create([
            'class_id' => $class->id,
            'fee_type_id' => $feeType->id,
            'amount' => 500,
            'due_day' => 10,
            'session_year' => now()->year,
            'is_active' => true,
        ]);
    }

    $student = createMonthlyInvoiceTestStudent($class->id);

    app(GenerateMonthlyFeeInvoicesAction::class)->handle(now()->month, now()->year);

    Notification::assertSentToTimes($student->user, StudentFeeInvoiceGeneratedNotification::class, 2);
});

it('does not create duplicate invoices when run twice for the same month/year', function () {
    $class = Classes::create(['name' => 'Class Two', 'order' => 2]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 500,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    createMonthlyInvoiceTestStudent($class->id);

    app(GenerateMonthlyFeeInvoicesAction::class)->handle(now()->month, now()->year);
    $result = app(GenerateMonthlyFeeInvoicesAction::class)->handle(now()->month, now()->year);

    expect($result)->toBe(['generated' => 0, 'skipped' => 1])
        ->and(StudentFeeInvoice::count())->toBe(1);
});

it('scopes generation to a single class when a class id is given', function () {
    $classOne = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $classTwo = Classes::create(['name' => 'Class Four', 'order' => 4]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    foreach ([$classOne, $classTwo] as $class) {
        FeeStructure::create([
            'class_id' => $class->id,
            'fee_type_id' => $feeType->id,
            'amount' => 500,
            'due_day' => 10,
            'session_year' => now()->year,
            'is_active' => true,
        ]);
    }

    $studentInClassOne = createMonthlyInvoiceTestStudent($classOne->id);
    $studentInClassTwo = createMonthlyInvoiceTestStudent($classTwo->id);

    $result = app(GenerateMonthlyFeeInvoicesAction::class)->handle(now()->month, now()->year, $classOne->id);

    expect($result)->toBe(['generated' => 1, 'skipped' => 0])
        ->and(StudentFeeInvoice::where('student_id', $studentInClassOne->id)->exists())->toBeTrue()
        ->and(StudentFeeInvoice::where('student_id', $studentInClassTwo->id)->exists())->toBeFalse();
});

it('applies an active student fee discount when generating invoices', function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 1000,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    $student = createMonthlyInvoiceTestStudent($class->id);

    $discount = FeeDiscount::create([
        'name' => '10% Scholarship',
        'discount_type' => FeeDiscountType::Percent,
        'discount_value' => 10,
    ]);

    StudentFeeDiscount::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'discount_id' => $discount->id,
        'session_year' => now()->year,
    ]);

    app(GenerateMonthlyFeeInvoicesAction::class)->handle(now()->month, now()->year);

    $invoice = StudentFeeInvoice::where('student_id', $student->id)->first();

    expect((float) $invoice->discount_amount)->toBe(100.0)
        ->and((float) $invoice->net_amount)->toBe(900.0)
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid);
});
