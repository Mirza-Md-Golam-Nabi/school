<?php

use App\Enums\LeaveApplicationStatus;
use App\Enums\PermissionRegistry;
use App\Enums\UserType;
use App\Filament\Widgets\FinanceOverviewWidget;
use App\Filament\Widgets\IncomeExpenseChartWidget;
use App\Filament\Widgets\PendingAlertsWidget;
use App\Models\LeaveApplication;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ((new PermissionRegistry)() as $permissionSet) {
        foreach ($permissionSet as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }
    }
});

function widgetUser(string ...$permissions): User
{
    $user = User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('hides the finance widgets without the account transaction permission', function () {
    test()->actingAs(widgetUser());

    expect(FinanceOverviewWidget::canView())->toBeFalse()
        ->and(IncomeExpenseChartWidget::canView())->toBeFalse();
});

it('shows the finance widgets with the account transaction permission', function () {
    test()->actingAs(widgetUser('view_account_transactions'));

    expect(FinanceOverviewWidget::canView())->toBeTrue()
        ->and(IncomeExpenseChartWidget::canView())->toBeTrue();
});

it('shows a pending alert only to users who may open its page', function () {
    LeaveApplication::factory()->create(['status' => LeaveApplicationStatus::Pending]);

    test()->actingAs(widgetUser());
    Livewire::test(PendingAlertsWidget::class)->assertDontSee(__('Leave Application Pending'));

    test()->actingAs(widgetUser('view_leave_applications'));
    Livewire::test(PendingAlertsWidget::class)->assertSee(__('Leave Application Pending'));
});
