<?php

use App\Enums\PermissionRegistry;
use App\Enums\UserType;
use App\Filament\Resources\FeeTypes\FeeTypeResource;
use App\Filament\Resources\FeeTypes\Pages\ListFeeTypes;
use App\Models\FeeType;
use App\Models\Permission;
use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
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

function entityPermissionUser(string ...$permissions): User
{
    $user = User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('hides edit and bulk delete from a user who may only view', function () {
    $feeType = FeeType::create(['name' => 'Tuition', 'is_monthly' => true, 'is_active' => true]);
    test()->actingAs(entityPermissionUser('view_fee_types'));

    Livewire::test(ListFeeTypes::class)
        ->assertActionHidden(TestAction::make(EditAction::class)->table($feeType))
        ->assertActionHidden(TestAction::make(DeleteBulkAction::class)->table()->bulk());
});

it('shows edit and bulk delete once the permissions are granted', function () {
    $feeType = FeeType::create(['name' => 'Tuition', 'is_monthly' => true, 'is_active' => true]);
    test()->actingAs(entityPermissionUser('view_fee_types', 'edit_fee_types', 'delete_fee_types'));

    Livewire::test(ListFeeTypes::class)
        ->assertActionVisible(TestAction::make(EditAction::class)->table($feeType))
        ->assertActionVisible(TestAction::make(DeleteBulkAction::class)->table()->bulk());
});

it('blocks the edit page without the edit permission', function () {
    $feeType = FeeType::create(['name' => 'Tuition', 'is_monthly' => true, 'is_active' => true]);
    test()->actingAs(entityPermissionUser('view_fee_types'));

    test()->get(FeeTypeResource::getUrl('edit', ['record' => $feeType]))->assertForbidden();
});
