<?php

use App\Enums\EmploymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\SalaryInvoice;
use App\Models\SalaryPayment;
use App\Models\SchoolAccount;
use App\Models\TeacherProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createInvoiceStatusTestAccount(): SchoolAccount
{
    return SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
}

function createInvoiceStatusTestInvoice(float $netAmount = 500): SalaryInvoice
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
        'status' => InvoiceStatus::Unpaid,
        'is_manual' => true,
    ]);
}

function payInvoiceStatusTestInvoice(SalaryInvoice $invoice, float $amount, SchoolAccount $account): SalaryPayment
{
    return SalaryPayment::create([
        'salary_invoice_id' => $invoice->id,
        'amount_paid' => $amount,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);
}

it('marks the invoice paid when the full amount is paid', function () {
    $account = createInvoiceStatusTestAccount();
    $invoice = createInvoiceStatusTestInvoice(500);

    payInvoiceStatusTestInvoice($invoice, 500, $account);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('marks the invoice partial when less than the full amount is paid', function () {
    $account = createInvoiceStatusTestAccount();
    $invoice = createInvoiceStatusTestInvoice(500);

    payInvoiceStatusTestInvoice($invoice, 300, $account);

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Partial)
        ->due_amount->toBe(200.0);
});

it('rolls back a paid invoice to unpaid when its only payment is deleted', function () {
    $account = createInvoiceStatusTestAccount();
    $invoice = createInvoiceStatusTestInvoice(500);
    $payment = payInvoiceStatusTestInvoice($invoice, 500, $account);

    $payment->delete();

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Unpaid)
        ->total_paid->toBe(0.0);
});

it('prevents deleting an invoice that has a payment', function () {
    $account = createInvoiceStatusTestAccount();
    $invoice = createInvoiceStatusTestInvoice(500);
    payInvoiceStatusTestInvoice($invoice, 200, $account);

    expect(fn () => $invoice->delete())->toThrow(RuntimeException::class);
    expect(SalaryInvoice::find($invoice->id))->not->toBeNull();
});

it('allows deleting an invoice that has no payment', function () {
    $invoice = createInvoiceStatusTestInvoice(500);

    $invoice->delete();

    expect(SalaryInvoice::find($invoice->id))->toBeNull();
});
