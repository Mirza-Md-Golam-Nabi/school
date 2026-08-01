<?php

use App\Actions\ProcessIndividualSalaryPaymentAction;
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

function createIndividualPaymentTestInvoice(TeacherProfile $teacher, int $month, int $year, float $netAmount): SalaryInvoice
{
    return SalaryInvoice::create([
        'invoice_no' => 'SAL-'.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'month' => $month,
        'year' => $year,
        'gross_amount' => $netAmount,
        'deduction_amount' => 0,
        'net_amount' => $netAmount,
        'status' => InvoiceStatus::Unpaid,
        'is_manual' => true,
    ]);
}

it('pays a single invoice in full without creating a bulk wrapper', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $invoice = createIndividualPaymentTestInvoice($teacher, 7, 2026, 20000);

    $payments = app(ProcessIndividualSalaryPaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 20000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    expect($payments)->toHaveCount(1);
    expect($payments[0]->bulk_payment_id)->toBeNull();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('allows a partial payment on a single invoice without blocking on the shortfall', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $invoice = createIndividualPaymentTestInvoice($teacher, 7, 2026, 20000);

    $payments = app(ProcessIndividualSalaryPaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 5000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    expect($payments)->toHaveCount(1);
    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Partial)
        ->due_amount->toBe(15000.0);
});

it('distributes one lump sum across multiple months oldest-first, leaving the last one partial', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    $may = createIndividualPaymentTestInvoice($teacher, 5, 2026, 20000);
    $june = createIndividualPaymentTestInvoice($teacher, 6, 2026, 20000);
    $july = createIndividualPaymentTestInvoice($teacher, 7, 2026, 20000);

    $payments = app(ProcessIndividualSalaryPaymentAction::class)->handle([
        'invoice_ids' => [$july->id, $may->id, $june->id],
        'amount_paid' => 45000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    expect($payments)->toHaveCount(3);

    $bulkPaymentId = $payments[0]->bulk_payment_id;
    expect($bulkPaymentId)->not->toBeNull();
    foreach ($payments as $payment) {
        expect($payment->bulk_payment_id)->toBe($bulkPaymentId);
    }

    expect($may->fresh()->status)->toBe(InvoiceStatus::Paid);
    expect($june->fresh()->status)->toBe(InvoiceStatus::Paid);
    expect($july->fresh())
        ->status->toBe(InvoiceStatus::Partial)
        ->due_amount->toBe(15000.0);

    expect((float) $account->fresh()->current_balance)->toBe(55000.0);
});
