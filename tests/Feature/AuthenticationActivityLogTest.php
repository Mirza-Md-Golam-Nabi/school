<?php

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs a successful login attempt', function () {
    $user = User::factory()->create([
        'user_type' => UserType::Admin,
        'is_active' => true,
        'password' => bcrypt('password'),
    ]);

    Auth::attempt(['email' => $user->email, 'password' => 'password']);

    $activities = Activity::where('log_name', 'auth')
        ->where('event', 'login')
        ->get();

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->causer_id)->toBe($user->id);
});

it('logs a successful logout', function () {
    $user = User::factory()->create([
        'user_type' => UserType::Admin,
        'is_active' => true,
    ]);

    Auth::login($user);
    Auth::logout();

    $activities = Activity::where('log_name', 'auth')
        ->where('event', 'logout')
        ->get();

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->causer_id)->toBe($user->id);
});

it('logs a failed login attempt without leaking the password', function () {
    $user = User::factory()->create([
        'user_type' => UserType::Admin,
        'is_active' => true,
        'password' => bcrypt('password'),
    ]);

    Auth::attempt(['email' => $user->email, 'password' => 'wrong-password']);

    $activities = Activity::where('log_name', 'auth')
        ->where('event', 'failed_login')
        ->get();

    expect($activities)->toHaveCount(1);
    expect($activities->first()->properties->get('identifier'))->toBe($user->email);
    expect($activities->first()->properties->toArray())->not->toHaveKey('password');
});
