<?php

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Filament\Resources\AccountTransactions\Pages\ListAccountTransactions;
use App\Filament\Resources\AccountTransactions\Widgets\IncomeExpenseOverviewWidget;
use App\Filament\Resources\AccountTransactions\Widgets\TransactionTypeBreakdownWidget;
use App\Models\AccountTransaction;
use App\Models\SchoolAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createOverviewTestTransaction(SchoolAccount $account, TransactionType $type, float $amount, string $date, TransactionSource $source = TransactionSource::Other): AccountTransaction
{
    return AccountTransaction::create([
        'account_id' => $account->id,
        'transaction_type' => $type,
        'source_type' => $source,
        'amount' => $amount,
        'transaction_date' => $date,
        'description' => 'Test transaction',
    ]);
}

it('shows only today\'s income and expense totals by default', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);

    createOverviewTestTransaction($account, TransactionType::Income, 1000, now()->toDateString());
    createOverviewTestTransaction($account, TransactionType::Expense, 400, now()->toDateString());
    createOverviewTestTransaction($account, TransactionType::Income, 5000, now()->subDays(5)->toDateString());

    Livewire::test(IncomeExpenseOverviewWidget::class)
        ->assertSet('startDate', now()->toDateString())
        ->assertSet('endDate', now()->toDateString())
        ->assertSee('1,000.00')
        ->assertSee('400.00')
        ->assertDontSee('5,000.00');
});

it('recalculates income and expense totals when the date range changes', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);

    createOverviewTestTransaction($account, TransactionType::Income, 2000, '2026-05-10');
    createOverviewTestTransaction($account, TransactionType::Expense, 750, '2026-05-20');

    Livewire::test(IncomeExpenseOverviewWidget::class)
        ->set('startDate', '2026-05-01')
        ->set('endDate', '2026-05-31')
        ->assertSee('2,000.00')
        ->assertSee('750.00');
});

it('builds income and expense links that carry the type and date range as plain query params', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    Livewire::test(IncomeExpenseOverviewWidget::class)
        ->set('startDate', '2026-05-01')
        ->set('endDate', '2026-05-31')
        ->assertViewHas('incomeUrl', fn (string $url) => str_contains($url, 'type=income')
            && str_contains($url, 'from=2026-05-01')
            && str_contains($url, 'until=2026-05-31'))
        ->assertViewHas('expenseUrl', fn (string $url) => str_contains($url, 'type=expense'));
});

it('hides the overview widget once a single type is selected', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    Livewire::test(IncomeExpenseOverviewWidget::class, ['type' => 'income'])
        ->assertViewHas('isVisible', false);
});

it('shows only the selected type\'s records in the table, never both', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);

    $income = createOverviewTestTransaction($account, TransactionType::Income, 2000, '2026-05-10');
    $expense = createOverviewTestTransaction($account, TransactionType::Expense, 750, '2026-05-10');

    Livewire::test(ListAccountTransactions::class, ['type' => 'income', 'from' => '2026-05-01', 'until' => '2026-05-31'])
        ->assertCanSeeTableRecords([$income])
        ->assertCanNotSeeTableRecords([$expense]);

    Livewire::test(ListAccountTransactions::class, ['type' => 'expense', 'from' => '2026-05-01', 'until' => '2026-05-31'])
        ->assertCanSeeTableRecords([$expense])
        ->assertCanNotSeeTableRecords([$income]);
});

it('filters the account ledger table using the date range filter directly', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);

    $inRange = createOverviewTestTransaction($account, TransactionType::Income, 2000, '2026-05-10');
    $outOfRange = createOverviewTestTransaction($account, TransactionType::Income, 9999, '2026-06-10');

    Livewire::test(ListAccountTransactions::class)
        ->filterTable('transaction_date', ['from' => '2026-05-01', 'until' => '2026-05-31'])
        ->assertCanSeeTableRecords([$inRange])
        ->assertCanNotSeeTableRecords([$outOfRange]);
});

it('hides the breakdown widget when no type is selected', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    Livewire::test(TransactionTypeBreakdownWidget::class)
        ->assertViewHas('isVisible', false);
});

it('breaks income down into Fee Payment vs Others, merging every admin-created fund category into Others', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);

    createOverviewTestTransaction($account, TransactionType::Income, 3000, '2026-05-10', TransactionSource::FeePayment);
    createOverviewTestTransaction($account, TransactionType::Income, 500, '2026-05-11', TransactionSource::Other);
    createOverviewTestTransaction($account, TransactionType::Income, 200, '2026-05-12', TransactionSource::Other);

    Livewire::test(TransactionTypeBreakdownWidget::class, ['type' => 'income', 'startDate' => '2026-05-01', 'endDate' => '2026-05-31'])
        ->assertViewHas('isVisible', true)
        ->assertViewHas('breakdown', ['Fee Payment' => 3000.0, 'Others' => 700.0]);
});

it('breaks expense down into Salary vs Others', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);

    createOverviewTestTransaction($account, TransactionType::Expense, 4000, '2026-05-10', TransactionSource::Salary);
    createOverviewTestTransaction($account, TransactionType::Expense, 300, '2026-05-11', TransactionSource::Other);

    Livewire::test(TransactionTypeBreakdownWidget::class, ['type' => 'expense', 'startDate' => '2026-05-01', 'endDate' => '2026-05-31'])
        ->assertViewHas('isVisible', true)
        ->assertViewHas('breakdown', ['Salary' => 4000.0, 'Others' => 300.0]);
});
