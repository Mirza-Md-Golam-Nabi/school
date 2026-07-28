<?php

use App\Enums\EmploymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionSource;
use App\Models\AccountTransaction;
use App\Models\SalaryInvoice;
use App\Models\SalaryPayment;
use App\Models\SchoolAccount;
use App\Models\TeacherProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSalaryTestAccount(float $balance = 100000): SchoolAccount
{
    return SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => $balance]);
}

function createSalaryTestInvoice(float $netAmount = 20000, InvoiceStatus $status = InvoiceStatus::Unpaid): SalaryInvoice
{
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    return SalaryInvoice::create([
        'invoice_no' => 'SAL-2026-07-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'month' => 7,
        'year' => 2026,
        'gross_amount' => $netAmount,
        'deduction_amount' => 0,
        'net_amount' => $netAmount,
        'status' => $status,
        'is_manual' => true,
    ]);
}

function createSalaryTestPayment(SalaryInvoice $invoice, float $amount, SchoolAccount $account): SalaryPayment
{
    return SalaryPayment::create([
        'salary_invoice_id' => $invoice->id,
        'amount_paid' => $amount,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);
}

it('creates an account transaction and decrements the school account balance when a salary payment is created', function () {
    $account = createSalaryTestAccount(100000);
    $invoice = createSalaryTestInvoice(20000);

    $payment = createSalaryTestPayment($invoice, 20000, $account);

    $transaction = AccountTransaction::where('source_type', TransactionSource::Salary)
        ->where('source_id', $payment->id)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->amount)->toBe(20000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(80000.0);
});

it('reverses the account transaction and restores the balance when a salary payment is deleted', function () {
    $account = createSalaryTestAccount(100000);
    $invoice = createSalaryTestInvoice(20000);
    $payment = createSalaryTestPayment($invoice, 20000, $account);

    expect((float) $account->fresh()->current_balance)->toBe(80000.0);

    $payment->delete();

    expect((float) $account->fresh()->current_balance)->toBe(100000.0)
        ->and(AccountTransaction::where('source_type', TransactionSource::Salary)->where('source_id', $payment->id)->exists())->toBeFalse();
});

it('resyncs the account transaction and balance when a salary payment amount is edited', function () {
    $account = createSalaryTestAccount(100000);
    $invoice = createSalaryTestInvoice(20000);
    $payment = createSalaryTestPayment($invoice, 20000, $account);

    $payment->update(['amount_paid' => 15000]);

    $transaction = AccountTransaction::where('source_type', TransactionSource::Salary)
        ->where('source_id', $payment->id)
        ->first();

    expect((float) $transaction->amount)->toBe(15000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(85000.0);
});

it('moves the balance effect to the new account when a salary payment account is edited', function () {
    $accountA = createSalaryTestAccount(100000);
    $accountB = createSalaryTestAccount(50000);
    $invoice = createSalaryTestInvoice(20000);
    $payment = createSalaryTestPayment($invoice, 20000, $accountA);

    $payment->update(['school_account_id' => $accountB->id]);

    expect((float) $accountA->fresh()->current_balance)->toBe(100000.0)
        ->and((float) $accountB->fresh()->current_balance)->toBe(30000.0);
});
