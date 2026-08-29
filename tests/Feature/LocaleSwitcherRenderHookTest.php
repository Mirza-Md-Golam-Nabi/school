<?php

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders an English and a Bengali button for an authenticated admin', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('fi-theme-switcher', false)
        ->assertSee('English')
        ->assertSee('বাংলা')
        ->assertSee(route('locale.switch', 'en'), false)
        ->assertSee(route('locale.switch', 'bn'), false);
});

it('marks the active locale button', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin)
        ->withSession(['locale' => 'bn'])
        ->get('/admin')
        ->assertOk()
        ->assertSeeInOrder(['fi-active', 'বাংলা'], false);
});
