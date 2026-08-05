<?php

use App\Enums\UserType;
use App\Filament\Resources\FeePayments\Pages\CreateFeePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the received_by field as disabled and pre-filled with the authenticated user on the create page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true, 'name' => 'Karim Admin']));

    $response = $this->actingAs($admin)->get(CreateFeePayment::getUrl());

    $response->assertOk()
        ->assertSee('Received By')
        ->assertSee('Karim Admin');
});
