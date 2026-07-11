<?php

use App\Enums\UserType;
use App\Filament\Resources\FeePayments\Pages\CreateFeePayment;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the create page with a selectable class list when no class_id is given in the URL', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $class = Classes::create(['name' => 'Class Eleven', 'order' => 11]);

    $response = $this->actingAs($admin)->get(CreateFeePayment::getUrl());

    $response->assertOk()
        ->assertSee('Class')
        ->assertSee($class->name);
});

it('still pre-fills the class from the class_id query parameter', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $class = Classes::create(['name' => 'Class Twelve', 'order' => 12]);

    $response = $this->actingAs($admin)->get(CreateFeePayment::getUrl(['class_id' => $class->id]));

    $response->assertOk()->assertSee($class->name);
});
