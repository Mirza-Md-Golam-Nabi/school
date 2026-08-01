<?php

use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Filament\Resources\AccountTransactions\Pages\ListAccountTransactions;
use App\Filament\Resources\FundTransactions\Pages\CreateFundTransaction;
use App\Filament\Resources\FundTransactions\Pages\ListFundTransactions;
use App\Filament\Resources\TransactionCategories\Pages\CreateTransactionCategory;
use App\Filament\Resources\TransactionCategories\Pages\ListTransactionCategories;
use App\Models\SchoolAccount;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingAsFundAdmin(): User
{
    return User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
}

it('renders the transaction categories list and create pages', function () {
    $admin = actingAsFundAdmin();

    $this->actingAs($admin)->get(ListTransactionCategories::getUrl())->assertOk();
    $this->actingAs($admin)->get(CreateTransactionCategory::getUrl())->assertOk();
});

it('renders the fund transactions list and create pages', function () {
    $admin = actingAsFundAdmin();
    SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    TransactionCategory::create(['name' => 'Donation', 'type' => TransactionType::Income, 'is_active' => true]);

    $this->actingAs($admin)->get(ListFundTransactions::getUrl())->assertOk();
    $this->actingAs($admin)->get(CreateFundTransaction::getUrl())->assertOk()->assertSee('Vendor / Donor Name');
});

it('renders the account ledger list page', function () {
    $admin = actingAsFundAdmin();

    $this->actingAs($admin)->get(ListAccountTransactions::getUrl())->assertOk();
});
