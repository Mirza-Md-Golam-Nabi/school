<?php

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Widgets\AcademicQuickActionsWidget;
use App\Filament\Widgets\AcademicStatsWidget;
use App\Filament\Widgets\FinanceOverviewWidget;
use App\Filament\Widgets\IncomeExpenseChartWidget;
use App\Filament\Widgets\PendingAlertsWidget;
use App\Models\AccountTransaction;
use App\Models\SchoolAccount;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $admin = grantSuperAdmin(User::factory()->create([
        'user_type' => UserType::Admin,
        'is_active' => true,
    ]));

    test()->actingAs($admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders the admin dashboard page', function () {
    Livewire::test(Dashboard::class)->assertOk();
});

it('renders every academic and finance dashboard widget', function () {
    Livewire::test(AcademicStatsWidget::class)->assertOk();
    Livewire::test(AcademicQuickActionsWidget::class)->assertOk();
    Livewire::test(FinanceOverviewWidget::class)->assertOk();
    Livewire::test(IncomeExpenseChartWidget::class)->assertOk();
    Livewire::test(PendingAlertsWidget::class)->assertOk();
});

it('shows the all-clear message when there are no pending tasks', function () {
    Livewire::test(PendingAlertsWidget::class)
        ->assertSee('সব ঠিক আছে');
});

it('sums this month income and expense in the finance widget', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund']);

    AccountTransaction::create([
        'account_id' => $account->id,
        'transaction_type' => TransactionType::Income,
        'source_type' => TransactionSource::Other,
        'amount' => 5000,
        'transaction_date' => now(),
    ]);

    AccountTransaction::create([
        'account_id' => $account->id,
        'transaction_type' => TransactionType::Expense,
        'source_type' => TransactionSource::Other,
        'amount' => 2000,
        'transaction_date' => now(),
    ]);

    Livewire::test(FinanceOverviewWidget::class)
        ->assertSee('5,000')
        ->assertSee('2,000')
        ->assertSee('3,000');
});
