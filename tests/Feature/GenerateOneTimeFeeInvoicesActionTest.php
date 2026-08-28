<?php

use App\Actions\GenerateOneTimeFeeInvoicesAction;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
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

function createOneTimeInvoiceTestStudent(int $classId): StudentProfile
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

it('generates a one-time invoice for every active student in the fee structure class', function () {
    Notification::fake();

    $class = Classes::create(['name' => 'Class Six', 'order' => 6]);
    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);

    $structure = FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 2000,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    $student1 = createOneTimeInvoiceTestStudent($class->id);
    $student2 = createOneTimeInvoiceTestStudent($class->id);

    $result = app(GenerateOneTimeFeeInvoicesAction::class)->handle($structure);

    expect($result)->toBe(['generated' => 2, 'skipped' => 0]);

    $invoice1 = StudentFeeInvoice::where('student_id', $student1->id)->first();
    $invoice2 = StudentFeeInvoice::where('student_id', $student2->id)->first();

    expect($invoice1->month)->toBeNull()
        ->and($invoice1->year)->toBe(now()->year)
        ->and((float) $invoice1->net_amount)->toBe(2000.0)
        ->and($invoice1->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice2)->not->toBeNull();

    Notification::assertSentTo($student1->user, StudentFeeInvoiceGeneratedNotification::class);
    Notification::assertSentTo($student2->user, StudentFeeInvoiceGeneratedNotification::class);
});

it('does not create duplicate one-time invoices when run twice', function () {
    $class = Classes::create(['name' => 'Class Seven', 'order' => 7]);
    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);

    $structure = FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 2000,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    createOneTimeInvoiceTestStudent($class->id);

    app(GenerateOneTimeFeeInvoicesAction::class)->handle($structure);
    $result = app(GenerateOneTimeFeeInvoicesAction::class)->handle($structure);

    expect($result)->toBe(['generated' => 0, 'skipped' => 1])
        ->and(StudentFeeInvoice::count())->toBe(1);
});

it('refuses to generate one-time invoices for a monthly fee type', function () {
    $class = Classes::create(['name' => 'Class Eight', 'order' => 8]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    $structure = FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 500,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    app(GenerateOneTimeFeeInvoicesAction::class)->handle($structure);
})->throws(InvalidArgumentException::class);
