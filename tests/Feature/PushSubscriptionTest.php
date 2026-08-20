<?php

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\PushSubscription;

uses(RefreshDatabase::class);

it('stores a push subscription for the authenticated user', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    test()->actingAs($user)
        ->postJson('/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-1',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-token',
            ],
        ])
        ->assertOk()
        ->assertJson(['status' => 'subscribed']);

    $subscription = PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/test-endpoint-1')->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->subscribable_id)->toBe($user->id)
        ->and($subscription->subscribable_type)->toBe(User::class)
        ->and($subscription->public_key)->toBe('test-p256dh-key')
        ->and($subscription->auth_token)->toBe('test-auth-token');
});

it('rejects a push subscription request from a guest', function () {
    test()->postJson('/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-2',
        'keys' => ['p256dh' => 'key', 'auth' => 'token'],
    ])->assertForbidden();

    expect(PushSubscription::count())->toBe(0);
});

it('validates required fields when storing a push subscription', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    test()->actingAs($user)
        ->postJson('/push-subscriptions', ['endpoint' => 'not-a-valid-url'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

it('removes a push subscription for the authenticated user', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/test-endpoint-3', 'key', 'token');

    test()->actingAs($user)
        ->deleteJson('/push-subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-3'])
        ->assertOk()
        ->assertJson(['status' => 'unsubscribed']);

    expect(PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/test-endpoint-3')->exists())->toBeFalse();
});

it('rejects a push unsubscribe request from a guest', function () {
    test()->deleteJson('/push-subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-4'])
        ->assertForbidden();
});
