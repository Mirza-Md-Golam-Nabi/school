<?php

use App\Enums\UserType;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('binds ctrl+enter to the save changes button on the subject edit page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $subject = Subject::create([
        'name' => 'Bangla',
        'has_mcq' => false,
        'has_written' => true,
        'has_practical' => false,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(EditSubject::class, ['record' => $subject->id])
        ->assertSeeHtml('x-mousetrap.global.mod-s.mod-enter');
});
