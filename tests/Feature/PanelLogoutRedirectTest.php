<?php

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects to the home page after logging out of a panel', function (string $panel, UserType $userType): void {
    $this->actingAs(User::factory()->create(['user_type' => $userType, 'is_active' => true]));

    $this->post(route("filament.{$panel}.auth.logout"))
        ->assertRedirect(route('home'));

    $this->assertGuest();
})->with([
    'admin' => ['admin', UserType::Admin],
    'teacher' => ['teacher', UserType::Teacher],
    'student' => ['student', UserType::Student],
]);
