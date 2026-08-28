<?php

use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\AccountTransaction;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolAccount;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\SchoolAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSchoolAccountSeederTestPayment(string $receiptNo, float $amount): FeePayment
{
    $feeType = FeeType::firstOrCreate(['name' => 'Tuition'], ['is_monthly' => true, 'is_active' => true]);

    $user = User::factory()->create(['user_type' => UserType::Student]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 999),
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 1,
        'year' => now()->year,
        'original_amount' => $amount,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => $amount,
        'status' => InvoiceStatus::Unpaid,
    ]);

    // Created while the fee type has no school_account_id yet — the same state
    // FeePaymentSeeder's bulk insert leaves payments in — so no ledger entry exists.
    return FeePayment::create([
        'receipt_no' => $receiptNo,
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => $amount,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'received_by' => $user->id,
    ]);
}

it('creates the Main Account with a zero starting balance and routes every fee type into it', function () {
    FeeType::create(['name' => 'Tuition', 'is_monthly' => true, 'is_active' => true]);
    FeeType::create(['name' => 'Admission', 'is_monthly' => false, 'is_active' => true]);

    (new SchoolAccountSeeder)->run();

    $account = SchoolAccount::where('name', 'Main Account')->sole();

    expect(FeeType::where('school_account_id', $account->id)->count())->toBe(2);
});

it('backfills a ledger entry and balance for every fee payment already seeded', function () {
    $paymentOne = makeSchoolAccountSeederTestPayment('RCPT-000001', 500);
    $paymentTwo = makeSchoolAccountSeederTestPayment('RCPT-000002', 750);

    expect(AccountTransaction::count())->toBe(0);

    (new SchoolAccountSeeder)->run();

    $account = SchoolAccount::where('name', 'Main Account')->sole();

    expect((float) $account->current_balance)->toBe(1250.0)
        ->and(AccountTransaction::count())->toBe(2);

    $transactionOne = AccountTransaction::where('source_id', $paymentOne->id)->sole();
    $transactionTwo = AccountTransaction::where('source_id', $paymentTwo->id)->sole();

    expect((float) $transactionOne->amount)->toBe(500.0)
        ->and($transactionOne->account_id)->toBe($account->id)
        ->and((float) $transactionTwo->amount)->toBe(750.0);
});

it('does not double-post a payment that was already synced when the seeder runs again', function () {
    makeSchoolAccountSeederTestPayment('RCPT-000001', 500);

    (new SchoolAccountSeeder)->run();
    (new SchoolAccountSeeder)->run();

    $account = SchoolAccount::where('name', 'Main Account')->sole();

    expect((float) $account->current_balance)->toBe(500.0)
        ->and(AccountTransaction::count())->toBe(1);
});
