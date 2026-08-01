<?php

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use App\Models\FundTransaction;
use App\Models\SchoolAccount;
use App\Models\TransactionCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createFundTestAccount(float $balance = 100000): SchoolAccount
{
    return SchoolAccount::create(['name' => 'Development Fund', 'current_balance' => $balance]);
}

function createFundTestCategory(TransactionType $type): TransactionCategory
{
    return TransactionCategory::create([
        'name' => $type === TransactionType::Income ? 'Donation' : 'Maintenance',
        'type' => $type,
        'is_active' => true,
    ]);
}

function createFundTestTransaction(SchoolAccount $account, TransactionCategory $category, float $amount): FundTransaction
{
    return FundTransaction::create([
        'transaction_category_id' => $category->id,
        'school_account_id' => $account->id,
        'type' => $category->type,
        'title' => 'Test transaction',
        'amount' => $amount,
        'transaction_date' => now()->toDateString(),
    ]);
}

it('increments the account balance and posts a ledger entry for an income transaction', function () {
    $account = createFundTestAccount(100000);
    $category = createFundTestCategory(TransactionType::Income);

    $fundTransaction = createFundTestTransaction($account, $category, 5000);

    $ledgerEntry = AccountTransaction::where('source_type', TransactionSource::Other)
        ->where('source_id', $fundTransaction->id)
        ->first();

    expect($ledgerEntry)->not->toBeNull();
    expect($ledgerEntry->transaction_type)->toBe(TransactionType::Income);
    expect((float) $account->fresh()->current_balance)->toBe(105000.0);
});

it('decrements the account balance for an expense transaction', function () {
    $account = createFundTestAccount(100000);
    $category = createFundTestCategory(TransactionType::Expense);

    createFundTestTransaction($account, $category, 5000);

    expect((float) $account->fresh()->current_balance)->toBe(95000.0);
});

it('reverses the balance effect and removes the ledger entry when a fund transaction is deleted', function () {
    $account = createFundTestAccount(100000);
    $category = createFundTestCategory(TransactionType::Expense);

    $fundTransaction = createFundTestTransaction($account, $category, 5000);
    expect((float) $account->fresh()->current_balance)->toBe(95000.0);

    $fundTransaction->delete();

    expect((float) $account->fresh()->current_balance)->toBe(100000.0);
    expect(AccountTransaction::where('source_type', TransactionSource::Other)->where('source_id', $fundTransaction->id)->exists())->toBeFalse();
});

it('resyncs the balance when a fund transaction amount is edited', function () {
    $account = createFundTestAccount(100000);
    $category = createFundTestCategory(TransactionType::Expense);

    $fundTransaction = createFundTestTransaction($account, $category, 5000);
    expect((float) $account->fresh()->current_balance)->toBe(95000.0);

    $fundTransaction->update(['amount' => 8000]);

    expect((float) $account->fresh()->current_balance)->toBe(92000.0);
});

it('correctly flips the balance effect when a fund transaction type changes from expense to income', function () {
    $account = createFundTestAccount(100000);
    $expenseCategory = createFundTestCategory(TransactionType::Expense);
    $incomeCategory = createFundTestCategory(TransactionType::Income);

    $fundTransaction = createFundTestTransaction($account, $expenseCategory, 5000);
    expect((float) $account->fresh()->current_balance)->toBe(95000.0);

    $fundTransaction->update([
        'transaction_category_id' => $incomeCategory->id,
        'type' => TransactionType::Income,
    ]);

    expect((float) $account->fresh()->current_balance)->toBe(105000.0);
});

it('moves the balance effect to the new account when a fund transaction account is edited', function () {
    $accountA = createFundTestAccount(100000);
    $accountB = createFundTestAccount(50000);
    $category = createFundTestCategory(TransactionType::Expense);

    $fundTransaction = createFundTestTransaction($accountA, $category, 5000);

    $fundTransaction->update(['school_account_id' => $accountB->id]);

    expect((float) $accountA->fresh()->current_balance)->toBe(100000.0)
        ->and((float) $accountB->fresh()->current_balance)->toBe(45000.0);
});
