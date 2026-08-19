<?php

use App\Enums\UserType;
use App\Filament\Resources\SchoolAccounts\SchoolAccountResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the fee-types helper text with an English/Bengali toggle button on the create page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $response = test()->actingAs($admin)->get(SchoolAccountResource::getUrl('create'));

    $response->assertOk();

    // Both languages are present in the markup (Alpine toggles visibility client-side).
    $response->assertSee('Payments collected for the selected fee types', false);
    $response->assertSee('নির্বাচিত fee type-গুলোর জন্য সংগৃহীত payment', false);

    // The Alpine-driven toggle button itself is present.
    $response->assertSee('x-data="{ lang: \'en\' }"', false);
    $response->assertSee('lang = (lang === \'en\' ? \'bn\' : \'en\')', false);
});
