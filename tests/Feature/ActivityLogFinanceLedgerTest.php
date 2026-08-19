<?php

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Models\AccountTransaction;
use App\Models\FundTransaction;
use App\Models\SchoolAccount;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs transaction category creation and updates with a readable type label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $category = TransactionCategory::create(['name' => 'Donation', 'type' => TransactionType::Income, 'is_active' => true]);

    $createdActivity = Activity::where('log_name', 'transaction_category')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Created transaction category "Donation" (Income).');

    $category->update(['name' => 'Donations']);

    $updatedActivity = Activity::where('log_name', 'transaction_category')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated transaction category "Donations".');
});

it('logs fund transaction creation with resolved category and account labels, plus a matching ledger entry', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Development Fund', 'current_balance' => 100000]);
    $category = TransactionCategory::create(['name' => 'Donation', 'type' => TransactionType::Income, 'is_active' => true]);

    $fundTransaction = FundTransaction::create([
        'transaction_category_id' => $category->id,
        'school_account_id' => $account->id,
        'type' => TransactionType::Income,
        'title' => 'Alumni donation',
        'amount' => 5000,
        'transaction_date' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    $fundActivity = Activity::where('log_name', 'fund_transaction')->where('event', 'created')->first();

    expect($fundActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Recorded Income "Alumni donation" of ৳5,000.00 under "Donation".');

    expect($fundActivity->properties->get('attributes'))
        ->toMatchArray([
            'transaction_category_id' => $category->id,
            'transaction_category_id_label' => 'Donation',
            'school_account_id' => $account->id,
            'school_account_id_label' => 'Development Fund',
        ]);

    $ledgerEntry = AccountTransaction::where('source_type', TransactionSource::Other)
        ->where('source_id', $fundTransaction->id)
        ->first();

    $ledgerActivity = Activity::where('log_name', 'account_ledger')
        ->where('event', 'created')
        ->where('subject_id', $ledgerEntry->id)
        ->first();

    expect($ledgerActivity)->not->toBeNull()
        ->description->toBe('Created Income ledger entry of ৳5,000.00 in "Development Fund" (Other).');

    expect($ledgerActivity->properties->get('attributes'))
        ->toMatchArray([
            'account_id' => $account->id,
            'account_id_label' => 'Development Fund',
        ]);
});

it('logs a deleted ledger entry when the underlying fund transaction is removed', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $category = TransactionCategory::create(['name' => 'Maintenance', 'type' => TransactionType::Expense, 'is_active' => true]);

    $fundTransaction = FundTransaction::create([
        'transaction_category_id' => $category->id,
        'school_account_id' => $account->id,
        'type' => TransactionType::Expense,
        'title' => 'Repairs',
        'amount' => 2000,
        'transaction_date' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    $fundTransaction->delete();

    $fundDeletedActivity = Activity::where('log_name', 'fund_transaction')->where('event', 'deleted')->first();

    expect($fundDeletedActivity)->not->toBeNull()
        ->description->toBe('Deleted Expense "Repairs" (৳2,000.00).');

    $ledgerDeletedActivity = Activity::where('log_name', 'account_ledger')->where('event', 'deleted')->first();

    expect($ledgerDeletedActivity)->not->toBeNull()
        ->description->toBe('Deleted Expense ledger entry of ৳2,000.00 in "Main Fund" (Other).');
});
