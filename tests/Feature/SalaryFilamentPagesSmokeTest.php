<?php

use App\Enums\EmploymentStatus;
use App\Enums\UserType;
use App\Filament\Resources\SalaryBulkPayments\Pages\ListSalaryBulkPayments;
use App\Filament\Resources\SalaryComponents\Pages\CreateSalaryComponent;
use App\Filament\Resources\SalaryComponents\Pages\ListSalaryComponents;
use App\Filament\Resources\SalaryInvoices\Pages\ListSalaryInvoices;
use App\Filament\Resources\SalaryPayments\Pages\CreateSalaryPayment;
use App\Filament\Resources\SalaryPayments\Pages\ListSalaryPayments;
use App\Filament\Resources\SalaryStructures\Pages\CreateSalaryStructure;
use App\Filament\Resources\SalaryStructures\Pages\ListSalaryStructures;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingAsSalaryAdmin(): User
{
    return grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
}

it('renders the salary components list and create pages', function () {
    $admin = actingAsSalaryAdmin();

    $this->actingAs($admin)->get(ListSalaryComponents::getUrl())->assertOk();
    $this->actingAs($admin)->get(CreateSalaryComponent::getUrl())->assertOk();
});

it('renders the salary structures list and create pages', function () {
    $admin = actingAsSalaryAdmin();
    TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    $this->actingAs($admin)->get(ListSalaryStructures::getUrl())->assertOk();
    $this->actingAs($admin)->get(CreateSalaryStructure::getUrl())->assertOk()->assertSee('Assignment');
});

it('renders the salary invoices list page with its generate and manual-invoice header actions', function () {
    $admin = actingAsSalaryAdmin();

    $response = $this->actingAs($admin)->get(ListSalaryInvoices::getUrl());

    $response->assertOk()
        ->assertSee('Generate Monthly Invoices')
        ->assertSee('Create Manual Invoice');
});

it('renders the salary payments list and create pages', function () {
    $admin = actingAsSalaryAdmin();
    TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    $response = $this->actingAs($admin)->get(ListSalaryPayments::getUrl());
    $response->assertOk()->assertSee('Payroll Batch Payment');

    $this->actingAs($admin)->get(CreateSalaryPayment::getUrl())->assertOk();
});

it('renders the salary bulk payments list page', function () {
    $admin = actingAsSalaryAdmin();

    $this->actingAs($admin)->get(ListSalaryBulkPayments::getUrl())->assertOk();
});
