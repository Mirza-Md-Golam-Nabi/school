<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Filament\Resources\AccountTransactions\Pages\ListAccountTransactions;
use App\Models\AccountTransaction;
use App\Models\Classes;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\FundTransaction;
use App\Models\SalaryInvoice;
use App\Models\SalaryPayment;
use App\Models\SchoolAccount;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('resolves the paying student as the party for a fee payment ledger entry', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true, 'school_account_id' => $account->id]);
    $class = Classes::create(['name' => 'Class 6', 'order' => 6]);

    $studentUser = User::factory()->create(['user_type' => UserType::Student, 'name' => 'Rafiq Islam', 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'roll_no' => 12,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 1,
        'year' => now()->year,
        'original_amount' => 500,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 500,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $payment = FeePayment::create([
        'receipt_no' => 'RCPT-1',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    $ledgerEntry = AccountTransaction::where('source_id', $payment->id)->firstOrFail();

    expect($ledgerEntry->partyRoleLabel())->toBe('Paid By')
        ->and($ledgerEntry->resolvePartyLabel())->toBe('Class 6 - Rafiq Islam (Roll: 12)');
});

it('resolves the paid teacher as the party for a salary payment ledger entry', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'name' => 'Mr. Karim', 'is_active' => true]);
    $teacher = TeacherProfile::factory()->create(['user_id' => $teacherUser->id, 'status' => EmploymentStatus::Active]);

    $invoice = SalaryInvoice::create([
        'invoice_no' => 'SAL-2026-07-0001',
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'month' => 7,
        'year' => 2026,
        'gross_amount' => 20000,
        'deduction_amount' => 0,
        'net_amount' => 20000,
        'status' => InvoiceStatus::Unpaid,
        'is_manual' => true,
    ]);

    $payment = SalaryPayment::create([
        'salary_invoice_id' => $invoice->id,
        'amount_paid' => 20000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    $ledgerEntry = AccountTransaction::where('source_id', $payment->id)
        ->where('source_type', TransactionSource::Salary)
        ->firstOrFail();

    expect($ledgerEntry->partyRoleLabel())->toBe('Paid To')
        ->and($ledgerEntry->resolvePartyLabel())->toBe('Mr. Karim');
});

it('resolves the fund transaction party_name for income and expense fund transactions', function () {
    $account = SchoolAccount::create(['name' => 'Development Fund', 'current_balance' => 0]);
    $donationCategory = TransactionCategory::create(['name' => 'Donation', 'type' => TransactionType::Income, 'is_active' => true]);
    $expenseCategory = TransactionCategory::create(['name' => 'Maintenance', 'type' => TransactionType::Expense, 'is_active' => true]);

    $donation = FundTransaction::create([
        'transaction_category_id' => $donationCategory->id,
        'school_account_id' => $account->id,
        'type' => TransactionType::Income,
        'title' => 'Alumni donation',
        'amount' => 5000,
        'transaction_date' => now()->toDateString(),
        'party_name' => 'Alumni Association',
    ]);

    $expense = FundTransaction::create([
        'transaction_category_id' => $expenseCategory->id,
        'school_account_id' => $account->id,
        'type' => TransactionType::Expense,
        'title' => 'AC repair',
        'amount' => 2000,
        'transaction_date' => now()->toDateString(),
        'party_name' => 'ABC Repairs Ltd',
    ]);

    $donationLedgerEntry = AccountTransaction::where('source_id', $donation->id)->firstOrFail();
    $expenseLedgerEntry = AccountTransaction::where('source_id', $expense->id)->firstOrFail();

    expect($donationLedgerEntry->partyRoleLabel())->toBe('Received From')
        ->and($donationLedgerEntry->resolvePartyLabel())->toBe('Alumni Association');

    expect($expenseLedgerEntry->partyRoleLabel())->toBe('Paid To')
        ->and($expenseLedgerEntry->resolvePartyLabel())->toBe('ABC Repairs Ltd');
});

it('falls back to the fund transaction title when no party_name is set', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $category = TransactionCategory::create(['name' => 'Bank Charges', 'type' => TransactionType::Expense, 'is_active' => true]);

    $fundTransaction = FundTransaction::create([
        'transaction_category_id' => $category->id,
        'school_account_id' => $account->id,
        'type' => TransactionType::Expense,
        'title' => 'Monthly bank charge',
        'amount' => 100,
        'transaction_date' => now()->toDateString(),
    ]);

    $ledgerEntry = AccountTransaction::where('source_id', $fundTransaction->id)->firstOrFail();

    expect($ledgerEntry->resolvePartyLabel())->toBe('Monthly bank charge');
});

it('shows the party name on the account ledger listing page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Development Fund', 'current_balance' => 0]);
    $category = TransactionCategory::create(['name' => 'Donation', 'type' => TransactionType::Income, 'is_active' => true]);

    FundTransaction::create([
        'transaction_category_id' => $category->id,
        'school_account_id' => $account->id,
        'type' => TransactionType::Income,
        'title' => 'Alumni donation',
        'amount' => 5000,
        'transaction_date' => now()->toDateString(),
        'party_name' => 'Alumni Association',
    ]);

    Livewire::test(ListAccountTransactions::class)
        ->assertOk()
        ->assertSee('Alumni Association')
        ->assertSee('Received From');
});
