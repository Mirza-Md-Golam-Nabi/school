<?php

use App\Enums\UserType;
use App\Filament\Resources\Classes\Pages\ListClasses;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows every class on one page without pagination', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    // More than the default page size (10) to prove pagination isn't cutting the list off.
    $classes = collect(range(1, 12))
        ->map(fn (int $order): Classes => Classes::create(['name' => "Class {$order}", 'order' => $order, 'is_active' => true]));

    Livewire::test(ListClasses::class)
        ->assertOk()
        ->assertCanSeeTableRecords($classes);
});
