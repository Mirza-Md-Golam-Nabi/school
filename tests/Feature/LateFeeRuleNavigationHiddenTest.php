<?php

use App\Enums\UserType;
use App\Filament\Resources\LateFeeRules\LateFeeRuleResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hides late fee rules from the admin sidebar, without removing the page itself', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    expect(LateFeeRuleResource::shouldRegisterNavigation())->toBeFalse();

    // The page itself still works — only the sidebar link is hidden.
    test()->get(LateFeeRuleResource::getUrl('index'))->assertOk();

    // The dashboard's sidebar must not link to it.
    test()->get('/admin')
        ->assertOk()
        ->assertDontSee('href="'.LateFeeRuleResource::getUrl('index').'"', false);
});
