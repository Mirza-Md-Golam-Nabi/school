<?php

use App\Actions\ProcessFeePaymentAction;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\FeePaymentReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createNotificationTestStudent(): StudentProfile
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

function createNotificationTestInvoice(StudentProfile $student, FeeType $feeType, ?int $month, float $amount): StudentFeeInvoice
{
    return StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => $month,
        'year' => now()->year,
        'original_amount' => $amount,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => $amount,
        'status' => InvoiceStatus::Unpaid,
    ]);
}

it('sends a payment notification for every invoice paid in a single submission', function () {
    Notification::fake();

    $student = createNotificationTestStudent();
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    $invoice1 = createNotificationTestInvoice($student, $feeType, 1, 100);
    $invoice2 = createNotificationTestInvoice($student, $feeType, 2, 100);

    app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => [$invoice1->id, $invoice2->id],
        'amount_paid' => 200,
        'receipt_no' => 'RCPT-TEST-1',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    Notification::assertSentTimes(FeePaymentReceivedNotification::class, 2);
    Notification::assertSentTo($student->user, FeePaymentReceivedNotification::class, fn ($n) => $n->payment->invoice_id === $invoice1->id);
    Notification::assertSentTo($student->user, FeePaymentReceivedNotification::class, fn ($n) => $n->payment->invoice_id === $invoice2->id);
});

it('stores a Filament-compatible database notification with invoice details', function () {
    $student = createNotificationTestStudent();
    $feeType = FeeType::create(['name' => 'Exam Fee - Annual', 'is_monthly' => false, 'is_active' => true]);
    $invoice = createNotificationTestInvoice($student, $feeType, null, 100);

    app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 100,
        'receipt_no' => 'RCPT-TEST-2',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Bkash,
        'payment_date' => now()->toDateString(),
    ]);

    $notification = $student->user->notifications()->latest()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['format'])->toBe('filament')
        ->and($notification->data['title'])->toBe('Fee Payment Received')
        ->and($notification->data['body'])->toContain('Exam Fee - Annual')
        ->and($notification->data['body'])->toContain((string) now()->year)
        ->and($notification->data['fee_type'])->toBe('Exam Fee - Annual')
        ->and((float) $notification->data['net_amount'])->toBe(100.0)
        ->and($notification->data['receipt_no'])->toBe('RCPT-TEST-2');
});

it('includes a view details action linking to the fee invoice with the invoice pre-opened', function () {
    $student = createNotificationTestStudent();
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $invoice = createNotificationTestInvoice($student, $feeType, 5, 120);

    app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 120,
        'receipt_no' => 'RCPT-TEST-4',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    $notification = $student->user->notifications()->latest()->first();

    expect($notification->data['actions'])->toHaveCount(1);

    $action = $notification->data['actions'][0];

    expect($action['label'])->toBe('View Details')
        ->and($action['url'])->toContain('/student/fee-invoices')
        ->and($action['url'])->toContain('tableAction=view')
        ->and($action['url'])->toContain('tableActionRecord='.$invoice->id);
});

it('includes the month in the notification body for monthly fee invoices', function () {
    $student = createNotificationTestStudent();
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $invoice = createNotificationTestInvoice($student, $feeType, 3, 150);

    app(ProcessFeePaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 150,
        'receipt_no' => 'RCPT-TEST-3',
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    $notification = $student->user->notifications()->latest()->first();

    expect($notification->data['body'])->toContain('Mar')
        ->and($notification->data['month'])->toBe(3);
});
