<?php

use App\Actions\ProcessPayrollBatchPaymentAction;
use App\Enums\EmploymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\SalaryInvoice;
use App\Models\SchoolAccount;
use App\Models\TeacherProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
});

function createBatchPaymentTestInvoice(float $netAmount): SalaryInvoice
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

it('fully pays every selected invoice across multiple teachers and groups them under one bulk payment', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 200000]);

    $invoiceA = createBatchPaymentTestInvoice(20000);
    $invoiceB = createBatchPaymentTestInvoice(15000);
    $invoiceC = createBatchPaymentTestInvoice(18000);

    $payments = app(ProcessPayrollBatchPaymentAction::class)->handle([
        'invoice_ids' => [$invoiceA->id, $invoiceB->id, $invoiceC->id],
        'payment_method' => PaymentMethod::BankTransfer,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    expect($payments)->toHaveCount(3);

    $bulkPaymentId = $payments[0]->bulk_payment_id;
    expect($bulkPaymentId)->not->toBeNull();
    foreach ($payments as $payment) {
        expect($payment->bulk_payment_id)->toBe($bulkPaymentId);
    }

    expect($invoiceA->fresh()->status)->toBe(InvoiceStatus::Paid);
    expect($invoiceB->fresh()->status)->toBe(InvoiceStatus::Paid);
    expect($invoiceC->fresh()->status)->toBe(InvoiceStatus::Paid);

    expect((float) $account->fresh()->current_balance)->toBe(200000 - 53000.0);
});

it('does not wrap a single-invoice batch in a bulk payment', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 50000]);
    $invoice = createBatchPaymentTestInvoice(20000);

    $payments = app(ProcessPayrollBatchPaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    expect($payments)->toHaveCount(1);
    expect($payments[0]->bulk_payment_id)->toBeNull();
});

it('reports the running total due for a set of selected invoices', function () {
    $invoiceA = createBatchPaymentTestInvoice(20000);
    $invoiceB = createBatchPaymentTestInvoice(15000);

    $total = app(ProcessPayrollBatchPaymentAction::class)->totalDue([$invoiceA->id, $invoiceB->id]);

    expect($total)->toBe(35000.0);
});
