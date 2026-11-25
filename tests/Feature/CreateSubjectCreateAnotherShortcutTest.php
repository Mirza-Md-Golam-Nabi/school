<?php

use App\Enums\UserType;
use App\Filament\Resources\Subjects\Pages\CreateSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('binds ctrl+enter to the create and create another button on the subject create page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    Livewire::actingAs($admin)
        ->test(CreateSubject::class)
        ->assertSeeHtml('x-mousetrap.global.mod-enter');
});
