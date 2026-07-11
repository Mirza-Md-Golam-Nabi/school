<?php

use App\Actions\ProcessFeePaymentAction;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\FeePaymentReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createRollbackTestStudent(): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function createRollbackTestInvoice(StudentProfile $student, float $netAmount, InvoiceStatus $status): StudentFeeInvoice
{
    $feeType = FeeType::create(['name' => 'Tuition Fee '.str()->random(4), 'is_monthly' => true, 'is_active' => true]);

    return StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 1,
        'year' => now()->year,
        'original_amount' => $netAmount,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => $netAmount,
        'status' => $status,
    ]);
}

function createRollbackTestPayment(StudentFeeInvoice $invoice, float $amount, string $receiptNo): FeePayment
{
    return FeePayment::create([
        'receipt_no' => $receiptNo,
        'student_id' => $invoice->student_id,
        'invoice_id' => $invoice->id,
        'amount_paid' => $amount,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);
}

it('rolls back a fully paid invoice to unpaid when its only payment is deleted', function () {
    $student = createRollbackTestStudent();
    $invoice = createRollbackTestInvoice($student, 500, InvoiceStatus::Paid);
    $payment = createRollbackTestPayment($invoice, 500, 'RCPT-1');

    $payment->delete();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('rolls back a paid invoice to partial when one of two payments is deleted', function () {
    $student = createRollbackTestStudent();
    $invoice = createRollbackTestInvoice($student, 500, InvoiceStatus::Paid);
    $first = createRollbackTestPayment($invoice, 300, 'RCPT-2');
    createRollbackTestPayment($invoice, 200, 'RCPT-2/2');

    $first->delete();

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Partial)
        ->total_paid->toBe(200.0);
});

it('rolls back a partial invoice to unpaid when its remaining payment is deleted', function () {
    $student = createRollbackTestStudent();
    $invoice = createRollbackTestInvoice($student, 500, InvoiceStatus::Partial);
    $payment = createRollbackTestPayment($invoice, 200, 'RCPT-3');

    $payment->delete();

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Unpaid)
        ->total_paid->toBe(0.0);
});

it('does not change the status of a waived invoice when a payment is deleted', function () {
    $student = createRollbackTestStudent();
    $invoice = createRollbackTestInvoice($student, 500, InvoiceStatus::Waived);
    $payment = createRollbackTestPayment($invoice, 200, 'RCPT-4');

    $payment->delete();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Waived);
});

it('deletes the associated fee payment notification when the payment is deleted', function () {
    $student = createRollbackTestStudent();
    $invoice = createRollbackTestInvoice($student, 500, InvoiceStatus::Paid);
    $payment = createRollbackTestPayment($invoice, 500, 'RCPT-6');

    $student->user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => FeePaymentReceivedNotification::class,
        'data' => ['receipt_no' => $payment->receipt_no, 'title' => 'Fee Payment Received'],
        'read_at' => null,
    ]);

    expect(DatabaseNotification::where('data->receipt_no', $payment->receipt_no)->count())->toBe(1);

    $payment->delete();

    expect(DatabaseNotification::where('data->receipt_no', $payment->receipt_no)->count())->toBe(0);
});

it('does not delete a notification belonging to a different payment', function () {
    $student = createRollbackTestStudent();
    $invoice = createRollbackTestInvoice($student, 500, InvoiceStatus::Paid);
    $paymentA = createRollbackTestPayment($invoice, 300, 'RCPT-7A');
    $paymentB = createRollbackTestPayment($invoice, 200, 'RCPT-7B');

    foreach ([$paymentA, $paymentB] as $payment) {
        $student->user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => FeePaymentReceivedNotification::class,
            'data' => ['receipt_no' => $payment->receipt_no],
            'read_at' => null,
        ]);
    }

    $paymentA->delete();

    expect(DatabaseNotification::where('data->receipt_no', $paymentA->receipt_no)->count())->toBe(0)
        ->and(DatabaseNotification::where('data->receipt_no', $paymentB->receipt_no)->count())->toBe(1);
});

it('deletes the real notification produced by the payment processing pipeline', function () {
    $student = createRollbackTestStudent();
    $invoice = createRollbackTestInvoice($student, 300, InvoiceStatus::Unpaid);

    $payment = app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 300,
        'receipt_no' => 'RCPT-8',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    expect($student->user->notifications()->count())->toBe(1);

    $payment->delete();

    expect($student->user->notifications()->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('rolls back correctly when payments are removed via bulk delete', function () {
    $student = createRollbackTestStudent();
    $invoice = createRollbackTestInvoice($student, 500, InvoiceStatus::Paid);
    $first = createRollbackTestPayment($invoice, 300, 'RCPT-5');
    $second = createRollbackTestPayment($invoice, 200, 'RCPT-5/2');

    FeePayment::destroy([$first->id, $second->id]);

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Unpaid)
        ->total_paid->toBe(0.0);
});
