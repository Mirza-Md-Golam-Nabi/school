<?php

use App\Enums\UserType;
use App\Filament\Resources\FeeStructures\Pages\CreateFeeStructure;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('labels the status radio as Active/Inactive instead of Yes/No', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    Livewire::test(CreateFeeStructure::class)
        ->assertSee('Active')
        ->assertSee('Inactive');
});
