<?php

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use App\Models\FundTransaction;
use App\Models\SchoolAccount;
use Database\Seeders\FundTransactionSeeder;
use Database\Seeders\TransactionCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates 25 fund transactions against Main Account and adjusts its balance correctly', function () {
    (new TransactionCategorySeeder)->run();

    $account = SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);

    (new FundTransactionSeeder)->run();

    expect(FundTransaction::count())->toBe(25)
        ->and(FundTransaction::where('school_account_id', $account->id)->count())->toBe(25)
        ->and(AccountTransaction::where('source_type', TransactionSource::Other)->count())->toBe(25);

    // Every fund transaction's type must match the category it was filed under.
    $mismatched = FundTransaction::with('category')
        ->get()
        ->filter(fn (FundTransaction $fundTransaction) => $fundTransaction->type !== $fundTransaction->category->type);

    expect($mismatched)->toBeEmpty();

    $totalIncome = (float) FundTransaction::where('type', TransactionType::Income)->sum('amount');
    $totalExpense = (float) FundTransaction::where('type', TransactionType::Expense)->sum('amount');

    expect((float) $account->fresh()->current_balance)->toBe(round($totalIncome - $totalExpense, 2));
});

it('does not create more transactions when the seeder runs again', function () {
    (new TransactionCategorySeeder)->run();
    SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);

    (new FundTransactionSeeder)->run();
    (new FundTransactionSeeder)->run();

    expect(FundTransaction::count())->toBe(25);
});

it('does nothing when no transaction categories exist yet', function () {
    SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);

    (new FundTransactionSeeder)->run();

    expect(FundTransaction::count())->toBe(0);
});
