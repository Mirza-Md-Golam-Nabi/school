<?php

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the webpush subscribe script for an authenticated admin', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('serviceWorker', false)
        ->assertSee('/push-subscriptions', false);
});

it('does not render the webpush subscribe script for a guest on the login page', function () {
    // The login page does register the service worker — for the PWA install
    // button (PwaInstallTest) — so check for markers only the push script has.
    test()->get('/admin/login')
        ->assertOk()
        ->assertDontSee('/push-subscriptions', false)
        ->assertDontSee('PushManager', false);
});
