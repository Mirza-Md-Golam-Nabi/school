<?php

use App\Actions\ResendUnacknowledgedPushNotificationsAction;
use App\Enums\UserType;
use App\Models\PushNotificationDelivery;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\PushSubscription;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

uses(RefreshDatabase::class);

class FakePushServiceClient implements ClientInterface
{
    /** @var array<int, RequestInterface> */
    public array $requests = [];

    public function __construct(private readonly int $statusCode = 201) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return new Response($this->statusCode);
    }
}

function createDeliveryTestSubscription(User $user): void
{
    // A structurally valid P-256 point + 16-byte auth secret — encryption runs
    // for real during flush(), so garbage keys would throw before the fake
    // HTTP client is ever reached.
    $user->updatePushSubscription(
        'https://fcm.googleapis.com/fcm/send/test-delivery-endpoint',
        'BHVc1fOSUTsacKj4PG5pXAV6JJ7gONwF6kc7raWNFzjucCVmx6IHW8TmIco4AZLITOei27D-UGp--BmJpD5htkk',
        'zr3j_oNGS7giiKa5Gf3xBg',
    );
}

function createDeliveryTestPayload(): array
{
    return ['title' => 'Test', 'body' => 'Test body', 'data' => ['delivery_token' => 'x']];
}

it('acknowledges a delivery as received', function () {
    $token = str_repeat('a', 64);
    $delivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', $token),
        'notifiable_type' => User::class,
        'notifiable_id' => User::factory()->create()->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now(),
    ]);

    test()->postJson('/push-notification-deliveries/acknowledge', ['delivery_token' => $token])
        ->assertOk()
        ->assertJson(['status' => 'acknowledged']);

    expect($delivery->fresh()->received_at)->not->toBeNull();
});

it('validates the delivery_token field when acknowledging', function () {
    test()->postJson('/push-notification-deliveries/acknowledge', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['delivery_token']);
});

it('silently ignores an acknowledgement for an unknown token', function () {
    test()->postJson('/push-notification-deliveries/acknowledge', ['delivery_token' => str_repeat('z', 64)])
        ->assertOk()
        ->assertJson(['status' => 'acknowledged']);

    expect(PushNotificationDelivery::count())->toBe(0);
});

it('does not resend a delivery that was sent less than 10 minutes ago', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    createDeliveryTestSubscription($user);

    PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('b', 64)),
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subMinutes(5),
    ]);

    $resent = (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], new FakePushServiceClient));

    expect($resent)->toBe(0);
});

it('does not resend a delivery that has already been received', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    createDeliveryTestSubscription($user);

    PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('c', 64)),
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subMinutes(20),
        'received_at' => now()->subMinutes(15),
    ]);

    $resent = (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], new FakePushServiceClient));

    expect($resent)->toBe(0);
});

it('skips a due delivery that has no push subscriptions', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    $delivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('d', 64)),
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subMinutes(20),
    ]);

    $resent = (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], new FakePushServiceClient));

    expect($resent)->toBe(0)
        ->and($delivery->fresh()->attempts)->toBe(1);
});

it('resends a due, unacknowledged delivery and increments its attempt count', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    createDeliveryTestSubscription($user);

    $delivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('e', 64)),
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subMinutes(15),
    ]);

    $client = new FakePushServiceClient(statusCode: 201);
    $resent = (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], $client));

    expect($resent)->toBe(1)
        ->and($client->requests)->toHaveCount(1);

    $delivery->refresh();

    expect($delivery->attempts)->toBe(2)
        ->and($delivery->last_sent_at->diffInSeconds(now()))->toBeLessThan(5)
        ->and($delivery->received_at)->toBeNull();
});

it('stops retrying a delivery once it reaches max_attempts', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    createDeliveryTestSubscription($user);

    $delivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('g', 64)),
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'payload' => createDeliveryTestPayload(),
        'attempts' => config('push_notifications.max_attempts'),
        'last_sent_at' => now()->subMinutes(20),
    ]);

    $resent = (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], new FakePushServiceClient));

    expect($resent)->toBe(0)
        ->and($delivery->fresh()->attempts)->toBe(config('push_notifications.max_attempts'));
});

it('deletes the subscription when the push service reports it as expired', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    createDeliveryTestSubscription($user);

    PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('f', 64)),
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subMinutes(15),
    ]);

    (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], new FakePushServiceClient(statusCode: 410)));

    expect(PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/test-delivery-endpoint')->exists())->toBeFalse();
});
