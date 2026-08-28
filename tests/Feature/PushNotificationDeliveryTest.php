<?php

use App\Actions\ResendUnacknowledgedPushNotificationsAction;
use App\Enums\UserType;
use App\Models\PushNotificationDelivery;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

/**
 * A structurally valid P-256 point + 16-byte auth secret — encryption runs
 * for real during flush(), so garbage keys would throw before the fake HTTP
 * client is ever reached.
 */
function createDeliveryTestSubscription(User $user, string $endpoint = 'https://fcm.googleapis.com/fcm/send/test-delivery-endpoint'): PushSubscription
{
    return $user->updatePushSubscription(
        $endpoint,
        'BHVc1fOSUTsacKj4PG5pXAV6JJ7gONwF6kc7raWNFzjucCVmx6IHW8TmIco4AZLITOei27D-UGp--BmJpD5htkk',
        'zr3j_oNGS7giiKa5Gf3xBg',
    );
}

function createDeliveryTestPayload(): array
{
    return ['title' => 'Test', 'body' => 'Test body', 'data' => ['delivery_token' => 'x']];
}

it('acknowledges a delivery as received', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $subscription = createDeliveryTestSubscription($user);

    $token = str_repeat('a', 64);
    $delivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', $token),
        'push_subscription_id' => $subscription->id,
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

it('tracks each device delivery independently, so one device acknowledging does not affect another', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $laptop = createDeliveryTestSubscription($user, 'https://fcm.googleapis.com/fcm/send/laptop-endpoint');
    $mobile = createDeliveryTestSubscription($user, 'https://fcm.googleapis.com/fcm/send/mobile-endpoint');

    $laptopToken = str_repeat('l', 64);
    $laptopDelivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', $laptopToken),
        'push_subscription_id' => $laptop->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now(),
    ]);

    $mobileToken = str_repeat('m', 64);
    $mobileDelivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', $mobileToken),
        'push_subscription_id' => $mobile->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now(),
    ]);

    // Only the laptop acknowledges receipt.
    test()->postJson('/push-notification-deliveries/acknowledge', ['delivery_token' => $laptopToken])
        ->assertOk();

    expect($laptopDelivery->fresh()->received_at)->not->toBeNull()
        ->and($mobileDelivery->fresh()->received_at)->toBeNull();
});

it('does not resend a delivery that was sent less than 10 minutes ago', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $subscription = createDeliveryTestSubscription($user);

    PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('b', 64)),
        'push_subscription_id' => $subscription->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subMinutes(5),
    ]);

    $resent = (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], new FakePushServiceClient));

    expect($resent)->toBe(0);
});

it('does not resend a delivery that has already been received', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $subscription = createDeliveryTestSubscription($user);

    PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('c', 64)),
        'push_subscription_id' => $subscription->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subMinutes(20),
        'received_at' => now()->subMinutes(15),
    ]);

    $resent = (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], new FakePushServiceClient));

    expect($resent)->toBe(0);
});

it('skips a due delivery whose device subscription no longer exists', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $subscription = createDeliveryTestSubscription($user);

    $delivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('d', 64)),
        'push_subscription_id' => $subscription->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subMinutes(20),
    ]);

    $subscription->delete();

    // Deleting the subscription cascades to its unresolved deliveries.
    expect(PushNotificationDelivery::find($delivery->id))->toBeNull();

    $resent = (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], new FakePushServiceClient));

    expect($resent)->toBe(0);
});

it('resends a due, unacknowledged delivery and increments its attempt count', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $subscription = createDeliveryTestSubscription($user);

    $delivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('e', 64)),
        'push_subscription_id' => $subscription->id,
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
    $subscription = createDeliveryTestSubscription($user);

    $delivery = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('g', 64)),
        'push_subscription_id' => $subscription->id,
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
    $subscription = createDeliveryTestSubscription($user);

    PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('f', 64)),
        'push_subscription_id' => $subscription->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subMinutes(15),
    ]);

    (new ResendUnacknowledgedPushNotificationsAction)->handle(new WebPush([], [], new FakePushServiceClient(statusCode: 410)));

    expect(PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/test-delivery-endpoint')->exists())->toBeFalse();
});

it('prunes push notification delivery records older than a week', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $subscription = createDeliveryTestSubscription($user);

    $old = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('h', 64)),
        'push_subscription_id' => $subscription->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now()->subWeeks(2),
        'received_at' => now()->subWeeks(2),
    ]);
    $old->forceFill(['created_at' => now()->subWeeks(2)])->save();

    $recent = PushNotificationDelivery::create([
        'token_hash' => hash('sha256', str_repeat('i', 64)),
        'push_subscription_id' => $subscription->id,
        'payload' => createDeliveryTestPayload(),
        'last_sent_at' => now(),
    ]);

    Artisan::call('webpush:prune-deliveries');

    expect(PushNotificationDelivery::find($old->id))->toBeNull()
        ->and(PushNotificationDelivery::find($recent->id))->not->toBeNull();
});
