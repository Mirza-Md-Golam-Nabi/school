<?php

use App\Actions\SyncFeeTypeAccountTransactionsAction;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\AccountTransaction;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolAccount;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSyncTestStudent(): StudentProfile
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

function createSyncTestInvoice(StudentProfile $student, FeeType $feeType, float $netAmount): StudentFeeInvoice
{
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
        'status' => InvoiceStatus::Unpaid,
    ]);
}

function createSyncTestPayment(StudentFeeInvoice $invoice, float $amount, string $receiptNo): FeePayment
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

it('posts an account transaction and increments the balance when a payment is made for an assigned fee type', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $feeType->update(['school_account_id' => $account->id]);

    $student = createSyncTestStudent();
    $invoice = createSyncTestInvoice($student, $feeType, 500);
    $payment = createSyncTestPayment($invoice, 500, 'RCPT-1');

    $transaction = AccountTransaction::where('source_id', $payment->id)->first();

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->amount)->toBe(500.0)
        ->and($transaction->account_id)->toBe($account->id)
        ->and((float) $account->fresh()->current_balance)->toBe(500.0);
});

it('does not post a transaction when the fee type has no assigned fund', function () {
    $feeType = FeeType::create(['name' => 'Miscellaneous Fee', 'is_monthly' => false, 'is_active' => true]);
    $student = createSyncTestStudent();
    $invoice = createSyncTestInvoice($student, $feeType, 300);
    $payment = createSyncTestPayment($invoice, 300, 'RCPT-2');

    expect(AccountTransaction::where('source_id', $payment->id)->exists())->toBeFalse();
});

it('reverses the transaction and balance when the payment is deleted', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $feeType->update(['school_account_id' => $account->id]);

    $student = createSyncTestStudent();
    $invoice = createSyncTestInvoice($student, $feeType, 500);
    $payment = createSyncTestPayment($invoice, 500, 'RCPT-3');

    $payment->delete();

    expect(AccountTransaction::where('source_id', $payment->id)->exists())->toBeFalse()
        ->and((float) $account->fresh()->current_balance)->toBe(0.0);
});

it('updates the transaction amount and balance when a payment amount is edited', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $feeType->update(['school_account_id' => $account->id]);

    $student = createSyncTestStudent();
    $invoice = createSyncTestInvoice($student, $feeType, 700);
    $payment = createSyncTestPayment($invoice, 500, 'RCPT-4');

    $payment->update(['amount_paid' => 700]);

    $transaction = AccountTransaction::where('source_id', $payment->id)->first();

    expect((float) $transaction->amount)->toBe(700.0)
        ->and((float) $account->fresh()->current_balance)->toBe(700.0);
});

it('does not backfill on its own when a fee type is simply assigned to a fund', function () {
    $feeType = FeeType::create(['name' => 'Exam Fee', 'is_monthly' => false, 'is_active' => true]);
    $student = createSyncTestStudent();
    $invoice = createSyncTestInvoice($student, $feeType, 400);
    $payment = createSyncTestPayment($invoice, 400, 'RCPT-5');

    $account = SchoolAccount::create(['name' => 'Exam Fund', 'current_balance' => 0]);
    $feeType->update(['school_account_id' => $account->id]);

    expect(AccountTransaction::where('source_id', $payment->id)->exists())->toBeFalse()
        ->and((float) $account->fresh()->current_balance)->toBe(0.0);
});

it('backfills historical payments only when the sync action is explicitly invoked', function () {
    $feeType = FeeType::create(['name' => 'Exam Fee', 'is_monthly' => false, 'is_active' => true]);
    $student = createSyncTestStudent();
    $invoice = createSyncTestInvoice($student, $feeType, 400);
    $payment = createSyncTestPayment($invoice, 400, 'RCPT-5');

    $account = SchoolAccount::create(['name' => 'Exam Fund', 'current_balance' => 0]);
    $feeType->update(['school_account_id' => $account->id]);

    app(SyncFeeTypeAccountTransactionsAction::class)->handle($feeType);

    $transaction = AccountTransaction::where('source_id', $payment->id)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->account_id)->toBe($account->id)
        ->and((float) $account->fresh()->current_balance)->toBe(400.0);
});

it('moves historical transactions and balances when explicitly resynced after a reassignment', function () {
    $accountA = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $accountB = SchoolAccount::create(['name' => 'Exam Fund', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Exam Fee', 'is_monthly' => false, 'is_active' => true]);
    $feeType->update(['school_account_id' => $accountA->id]);

    $student = createSyncTestStudent();
    $invoice = createSyncTestInvoice($student, $feeType, 600);
    $payment = createSyncTestPayment($invoice, 600, 'RCPT-6');
    app(SyncFeeTypeAccountTransactionsAction::class)->handle($feeType);

    expect((float) $accountA->fresh()->current_balance)->toBe(600.0);

    $feeType->update(['school_account_id' => $accountB->id]);
    app(SyncFeeTypeAccountTransactionsAction::class)->handle($feeType);

    $transaction = AccountTransaction::where('source_id', $payment->id)->first();

    expect($transaction->account_id)->toBe($accountB->id)
        ->and((float) $accountA->fresh()->current_balance)->toBe(0.0)
        ->and((float) $accountB->fresh()->current_balance)->toBe(600.0);
});

it('reverses transactions when explicitly resynced after a fee type is unassigned from its fund', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $feeType->update(['school_account_id' => $account->id]);

    $student = createSyncTestStudent();
    $invoice = createSyncTestInvoice($student, $feeType, 500);
    $payment = createSyncTestPayment($invoice, 500, 'RCPT-7');
    app(SyncFeeTypeAccountTransactionsAction::class)->handle($feeType);

    $feeType->update(['school_account_id' => null]);
    app(SyncFeeTypeAccountTransactionsAction::class)->handle($feeType);

    expect(AccountTransaction::where('source_id', $payment->id)->exists())->toBeFalse()
        ->and((float) $account->fresh()->current_balance)->toBe(0.0);
});

it('only resyncs payments in the requested years when scoped', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    $student = createSyncTestStudent();
    $invoiceLastYear = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 1,
        'year' => now()->year - 1,
        'original_amount' => 300,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 300,
        'status' => InvoiceStatus::Unpaid,
    ]);
    $paymentLastYear = createSyncTestPayment($invoiceLastYear, 300, 'RCPT-8A');
    $invoiceThisYear = createSyncTestInvoice($student, $feeType, 400);
    $paymentThisYear = createSyncTestPayment($invoiceThisYear, 400, 'RCPT-8B');

    $feeType->update(['school_account_id' => $account->id]);
    app(SyncFeeTypeAccountTransactionsAction::class)->handle($feeType, [now()->year]);

    expect(AccountTransaction::where('source_id', $paymentThisYear->id)->exists())->toBeTrue()
        ->and(AccountTransaction::where('source_id', $paymentLastYear->id)->exists())->toBeFalse()
        ->and((float) $account->fresh()->current_balance)->toBe(400.0);
});

it('handleFromPolicy skips resync entirely for "none" and resyncs for "all"/"years"', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $student = createSyncTestStudent();
    $invoice = createSyncTestInvoice($student, $feeType, 500);
    $payment = createSyncTestPayment($invoice, 500, 'RCPT-9');

    $feeType->update(['school_account_id' => $account->id]);
    app(SyncFeeTypeAccountTransactionsAction::class)->handleFromPolicy($feeType, 'none');

    expect(AccountTransaction::where('source_id', $payment->id)->exists())->toBeFalse();

    app(SyncFeeTypeAccountTransactionsAction::class)->handleFromPolicy($feeType, 'years', [now()->year]);

    expect(AccountTransaction::where('source_id', $payment->id)->exists())->toBeTrue()
        ->and((float) $account->fresh()->current_balance)->toBe(500.0);
});

it('computes the posted amount and available years for a fee type', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $feeType->update(['school_account_id' => $account->id]);

    $student = createSyncTestStudent();
    $invoiceLastYear = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 1,
        'year' => now()->year - 1,
        'original_amount' => 200,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 200,
        'status' => InvoiceStatus::Unpaid,
    ]);
    createSyncTestPayment($invoiceLastYear, 200, 'RCPT-10A');
    $invoiceThisYear = createSyncTestInvoice($student, $feeType, 300);
    createSyncTestPayment($invoiceThisYear, 300, 'RCPT-10B');

    expect($feeType->postedAmount())->toBe(500.0)
        ->and($feeType->postedAmount([now()->year]))->toBe(300.0)
        ->and($feeType->availableYears())->toBe([now()->year, now()->year - 1]);
});
