<?php

use App\Enums\EmploymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\SalaryInvoice;
use App\Models\SalaryInvoiceDeduction;
use App\Models\SalaryPayment;
use App\Models\SchoolAccount;
use App\Models\TeacherProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDeductionTestInvoice(float $grossAmount = 20000): SalaryInvoice
{
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    return SalaryInvoice::create([
        'invoice_no' => 'SAL-2026-07-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'month' => 7,
        'year' => 2026,
        'gross_amount' => $grossAmount,
        'deduction_amount' => 0,
        'net_amount' => $grossAmount,
        'status' => InvoiceStatus::Unpaid,
        'is_manual' => true,
    ]);
}

it('recalculates the invoice deduction and net amount when an ad-hoc deduction is added', function () {
    $invoice = createDeductionTestInvoice(20000);

    SalaryInvoiceDeduction::create([
        'salary_invoice_id' => $invoice->id,
        'amount' => 1500,
        'reason' => 'Late fine',
    ]);

    $fresh = $invoice->fresh();
    expect((float) $fresh->deduction_amount)->toBe(1500.0)
        ->and((float) $fresh->net_amount)->toBe(18500.0);
});

it('recalculates the invoice totals when a deduction is removed', function () {
    $invoice = createDeductionTestInvoice(20000);

    $deduction = SalaryInvoiceDeduction::create([
        'salary_invoice_id' => $invoice->id,
        'amount' => 1500,
        'reason' => 'Late fine',
    ]);

    $deduction->delete();

    $fresh = $invoice->fresh();
    expect((float) $fresh->deduction_amount)->toBe(0.0)
        ->and((float) $fresh->net_amount)->toBe(20000.0);
});

it('prevents adding a deduction to an invoice that already has a payment', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $invoice = createDeductionTestInvoice(20000);

    SalaryPayment::create([
        'salary_invoice_id' => $invoice->id,
        'amount_paid' => 5000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    expect(fn () => SalaryInvoiceDeduction::create([
        'salary_invoice_id' => $invoice->id,
        'amount' => 1000,
        'reason' => 'Late fine',
    ]))->toThrow(RuntimeException::class);
});
