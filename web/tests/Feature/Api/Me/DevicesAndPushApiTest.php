<?php

declare(strict_types=1);

use App\Application\Account\DeviceRegistry;
use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use App\Notifications\InvitationReceived;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Support\Notifications\FakePushSender;

/*
 * P6-09 (lot api-me) : appareils de l'application (`sync_devices` : POST me/devices idempotent, 403 révoqué,
 * 422 pris par un autre compte, DELETE → révoqué, X-Device-Id porté par le SOS) et abonnements push
 * (`push_subscriptions` : Web Push et FCM, hôtes push connus seulement, appareil partagé, suppression à la déconnexion,
 * à la révocation du jeton ou de l'appareil, endpoint jamais journalisé en clair, une Notification atteint l'abonnement
 * par le canal webpush, plus rien après suppression) ; signalement anonyme sans appareil ni trace (RG-01).
 */

const WEBPUSH_ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123-secret-endpoint';

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class]);

    $this->user = User::factory()->withProfile()->create();
    $this->user->assignRole('membre');
    $this->token = $this->user->createToken('Pixel 7')->plainTextToken;
    $this->uuid = (string) Str::uuid();
});

function webPushBody(array $overrides = []): array
{
    return array_replace_recursive([
        'kind' => 'webpush',
        'endpoint' => WEBPUSH_ENDPOINT,
        'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg'],
        'device_label' => 'Chrome sur Windows',
    ], $overrides);
}

function fakePush(): FakePushSender
{
    $fake = new FakePushSender;
    app()->instance(PushSender::class, $fake);
    app()->instance(FcmSender::class, $fake);

    return $fake;
}

it('répond 401 sans jeton', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
})->with([
    ['GET', '/api/v1/me/devices'], ['POST', '/api/v1/me/devices'], ['GET', '/api/v1/me/push-subscriptions'],
    ['POST', '/api/v1/me/push-subscriptions'], ['DELETE', '/api/v1/me/push-subscriptions'],
]);

// --- me/devices ------------------------------------------------------------------------------------------------------

it('enregistre l’appareil (201) puis le met à jour sans doublon (200)', function (): void {
    $created = $this->withToken($this->token)->postJson('/api/v1/me/devices', [
        'device_uuid' => Str::upper($this->uuid), 'label' => 'Pixel 7', 'platform' => 'Android 14', 'app_version' => '1.5.0',
    ])->assertCreated()
        ->assertHeader('Location', route('api.me.devices.index'))
        ->assertJsonPath('data.device_uuid', $this->uuid)
        ->assertJsonPath('data.revoked', false);

    expect($created->json('data.last_seen_at'))->not->toBeNull();

    $this->withToken($this->token)->postJson('/api/v1/me/devices', ['device_uuid' => $this->uuid, 'label' => 'Pixel 7 (Awa)', 'app_version' => '1.5.1'])
        ->assertOk()
        ->assertJsonPath('data.label', 'Pixel 7 (Awa)')
        ->assertJsonPath('data.app_version', '1.5.1');

    $this->withToken($this->token)->getJson('/api/v1/me/devices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);

    expect(SyncDevice::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'device.registered')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'device.registered')->value('detail'))->not->toContain($this->uuid);
});

it('valide l’appareil : UUID et libellé requis (422)', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/me/devices', ['device_uuid' => 'pas-un-uuid', 'platform' => str_repeat('x', 41)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device_uuid', 'label', 'platform']);
});

it('refuse un appareil d’un autre compte (422 device_taken) et un appareil révoqué (403 device_revoked)', function (): void {
    $other = User::factory()->create();
    SyncDevice::factory()->for($other)->create(['device_uuid' => $this->uuid]);

    $this->withToken($this->token)->postJson('/api/v1/me/devices', ['device_uuid' => $this->uuid, 'label' => 'Pixel 7'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'device_taken')
        ->assertJsonValidationErrors(['device_uuid' => __('account.devices.taken')]);

    $revoked = SyncDevice::factory()->for($this->user)->revoked()->create();

    $this->withToken($this->token)->postJson('/api/v1/me/devices', ['device_uuid' => $revoked->device_uuid, 'label' => 'Pixel 7'])
        ->assertForbidden()
        ->assertJsonPath('code', 'device_revoked')
        ->assertJsonPath('message', __('account.devices.revoked'));

    expect($revoked->fresh()->is_revoked)->toBeTrue()
        ->and($this->user->can('update', $revoked))->toBeFalse()
        ->and($this->user->can('delete', $revoked))->toBeTrue();
});

it('révoque mon appareil (204, jamais supprimé) ; celui d’un autre compte est introuvable (404)', function (): void {
    $mine = SyncDevice::factory()->for($this->user)->create();
    $foreign = SyncDevice::factory()->for(User::factory())->create();

    $this->withToken($this->token)->deleteJson("/api/v1/me/devices/{$mine->device_uuid}")->assertNoContent();
    $this->withToken($this->token)->deleteJson("/api/v1/me/devices/{$mine->device_uuid}")->assertNoContent();
    $this->withToken($this->token)->deleteJson("/api/v1/me/devices/{$foreign->device_uuid}")->assertNotFound();

    expect($mine->fresh()->is_revoked)->toBeTrue()
        ->and($foreign->fresh()->is_revoked)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'device.revoked')->count())->toBe(1);
});

it('rattache un SOS mobile à l’appareil annoncé par X-Device-Id, jamais à un appareil révoqué ou d’autrui', function (): void {
    $this->seed(ReferenceDataSeeder::class);
    Illuminate\Support\Facades\Notification::fake();
    $seen = now()->subDay()->startOfSecond();
    $device = SyncDevice::factory()->for($this->user)->create(['last_seen_at' => $seen]);

    $this->withToken($this->token)
        ->withHeaders([HandleIdempotencyKey::HEADER => (string) Str::uuid(), DeviceRegistry::HEADER => $device->device_uuid])
        ->postJson('/api/v1/sos', ['message' => 'Aide-moi'])
        ->assertCreated();

    $alert = Alert::query()->latest('id')->firstOrFail();
    // Résolution en lecture seule : `last_seen_at` n'est tenu que par POST me/devices.
    expect($alert->device_id)->toBe($device->id)
        ->and($alert->user_id)->toBe($this->user->id)
        ->and($device->fresh()->last_seen_at->equalTo($seen))->toBeTrue();

    $foreign = SyncDevice::factory()->for(User::factory())->create();
    $revoked = SyncDevice::factory()->for($this->user)->revoked()->create();

    foreach ([$foreign, $revoked] as $refused) {
        $this->withToken($this->token)
            ->withHeaders([HandleIdempotencyKey::HEADER => (string) Str::uuid(), DeviceRegistry::HEADER => $refused->device_uuid])
            ->postJson('/api/v1/sos', [])
            ->assertCreated();

        expect(Alert::query()->latest('id')->firstOrFail()->device_id)->toBeNull();
    }
});

it('ne consulte jamais l’appareil d’un signalement anonyme (RG-01) ; un signalement nominatif le garde', function (): void {
    $this->seed(ReferenceDataSeeder::class);
    Illuminate\Support\Facades\Notification::fake();
    $stamp = now()->subDays(3)->startOfSecond();
    $device = SyncDevice::factory()->for($this->user)->create(['last_seen_at' => $stamp]);
    $device->forceFill(['updated_at' => $stamp])->saveQuietly();
    $payload = ['type' => 'report', 'category' => 'physique', 'description' => Tests\Support\AlertFixtures::DESCRIPTION, 'zone_id' => Tests\Support\AlertFixtures::zone('Littoral')->id];

    $this->withToken($this->token)
        ->withHeaders([HandleIdempotencyKey::HEADER => (string) Str::uuid(), DeviceRegistry::HEADER => $device->device_uuid])
        ->postJson('/api/v1/signalements', $payload + ['anonymous' => true])
        ->assertCreated();

    $anonymous = Alert::query()->latest('id')->firstOrFail();
    $fresh = $device->fresh();
    expect($anonymous->device_id)->toBeNull()
        ->and($anonymous->user_id)->toBeNull()
        ->and($fresh->last_seen_at->equalTo($stamp))->toBeTrue()
        ->and($fresh->updated_at->equalTo($stamp))->toBeTrue();

    $this->withToken($this->token)
        ->withHeaders([HandleIdempotencyKey::HEADER => (string) Str::uuid(), DeviceRegistry::HEADER => $device->device_uuid])
        ->postJson('/api/v1/signalements', $payload + ['anonymous' => false])
        ->assertCreated();

    expect(Alert::query()->latest('id')->firstOrFail()->device_id)->toBe($device->id)
        ->and($device->fresh()->updated_at->equalTo($stamp))->toBeTrue();
});

// --- me/push-subscriptions -------------------------------------------------------------------------------------------

it('enregistre un abonnement Web Push (201), le reconnaît ensuite (200) et ne renvoie jamais l’endpoint', function (): void {
    $created = $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', webPushBody())->assertCreated();

    $subscription = PushSubscription::query()->sole();
    $created->assertJsonPath('data.kind', 'webpush')
        ->assertJsonPath('data.fingerprint', substr($subscription->endpoint_hash, 0, 12))
        ->assertJsonPath('data.device_label', 'Chrome sur Windows');

    expect($created->getContent())->not->toContain('abc123-secret-endpoint')
        ->and($subscription->public_key)->toStartWith('BNcR')
        ->and($subscription->auth_token)->toBe('tBHItJI5svbpez7KI4CCXg');

    $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', webPushBody(['device_label' => 'Chrome']))
        ->assertOk()->assertJsonPath('data.device_label', 'Chrome');

    config(['xomiton.notifications.push.webpush.vapid.public_key' => 'BPublicVapidKey']);
    $list = $this->withToken($this->token)->getJson('/api/v1/me/push-subscriptions')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.vapid_public_key', 'BPublicVapidKey');

    expect($list->getContent())->not->toContain('abc123-secret-endpoint')
        ->and(PushSubscription::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'push.subscribed')->get()->pluck('detail')->implode(' '))->not->toContain('abc123');
});

it('valide un abonnement : endpoint https et clés pour Web Push, jeton pour FCM (422)', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', ['kind' => 'webpush', 'endpoint' => 'http://exemple.bj/push'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);

    // SSRF : seuls les services push connus des navigateurs (config xomiton.notifications.push.webpush.allowed_hosts).
    foreach (['https://exemple.bj/push', 'https://127.0.0.1/push', 'https://fcm.googleapis.com.evil.bj/x', 'https://intranet.local/notify.windows.com'] as $endpoint) {
        $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', webPushBody(['endpoint' => $endpoint]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint' => __('account.push.endpoint_host')]);
    }

    foreach (['https://updates.push.services.mozilla.com/wpush/v2/abc', 'https://wns2-par02p.notify.windows.com/w/?token=abc', 'https://web.push.apple.com/QGx'] as $endpoint) {
        $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', webPushBody(['endpoint' => $endpoint]))->assertCreated();
    }

    expect(PushSubscription::query()->count())->toBe(3);

    $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', ['kind' => 'fcm'])
        ->assertUnprocessable()->assertJsonValidationErrors(['token']);

    $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', ['kind' => 'apns', 'token' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors(['kind']);
});

it('accepte les clés Web Push à plat et un jeton FCM', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', [
        'kind' => 'webpush', 'endpoint' => WEBPUSH_ENDPOINT, 'public_key' => 'BKey', 'auth_token' => 'secret',
    ])->assertCreated();

    $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', ['kind' => 'fcm', 'token' => 'fcm-token-123', 'device_label' => 'Pixel 7'])
        ->assertCreated()->assertJsonPath('data.kind', 'fcm');

    $fcm = PushSubscription::query()->fcm()->sole();
    expect($fcm->endpoint)->toBe('fcm-token-123')->and($fcm->public_key)->toBeNull()
        ->and(PushSubscription::query()->webPush()->sole()->auth_token)->toBe('secret');
});

it('fait passer l’abonnement d’un appareil partagé au compte connecté : l’ancien n’y est plus notifié', function (): void {
    $previous = User::factory()->withProfile()->create();
    PushSubscription::factory()->for($previous)->fcm()->create(['endpoint' => 'fcm-shared-token']);
    $fingerprint = substr(PushSubscription::hashEndpoint('fcm-shared-token'), 0, 12);

    // Journal : type et empreinte tronquée, jamais le jeton en clair.
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $context === ['kind' => 'fcm', 'subscription' => $fingerprint]
        && ! str_contains($message.json_encode($context), 'fcm-shared-token'));

    $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', ['kind' => 'fcm', 'token' => 'fcm-shared-token'])
        ->assertCreated();

    expect(PushSubscription::query()->sole()->user_id)->toBe($this->user->id)
        ->and($previous->pushSubscriptions()->count())->toBe(0);
});

it('livre une Notification à l’abonnement par le canal webpush, et plus rien après sa suppression', function (): void {
    $push = fakePush();

    $this->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', webPushBody())->assertCreated();
    $subscription = PushSubscription::query()->sole();

    $this->user->notify(new InvitationReceived(1, 'Mariam A.'));

    expect($push->calls)->toHaveCount(1)
        ->and($push->calls[0]['subscription'])->toBe($subscription->id)
        ->and($subscription->fresh()->last_used_at)->not->toBeNull();

    $this->withToken($this->token)->deleteJson('/api/v1/me/push-subscriptions', ['endpoint' => WEBPUSH_ENDPOINT])->assertNoContent();
    // Idempotent (déconnexion rejouée, onTokenRefresh) : toujours 204.
    $this->withToken($this->token)->deleteJson('/api/v1/me/push-subscriptions', ['endpoint' => WEBPUSH_ENDPOINT])->assertNoContent();

    $this->user->refresh()->notify(new InvitationReceived(2, 'Mariam A.'));

    expect($push->calls)->toHaveCount(1)
        ->and(PushSubscription::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'push.unsubscribed')->count())->toBe(1);
});

it('ne supprime jamais l’abonnement d’un autre compte', function (): void {
    $foreign = PushSubscription::factory()->for(User::factory())->fcm()->create(['endpoint' => 'fcm-foreign']);

    $this->withToken($this->token)->deleteJson('/api/v1/me/push-subscriptions', ['token' => 'fcm-foreign'])->assertNoContent();
    $this->withToken($this->token)->deleteJson('/api/v1/me/push-subscriptions', [])->assertUnprocessable()->assertJsonValidationErrors(['endpoint', 'token']);

    expect($foreign->fresh())->not->toBeNull()
        ->and($this->user->can('delete', $foreign))->toBeFalse();
});

it('supprime l’abonnement push de l’appareil à la déconnexion (push_token)', function (): void {
    PushSubscription::factory()->for($this->user)->fcm()->create(['endpoint' => 'fcm-mine']);
    PushSubscription::factory()->for($this->user)->fcm()->create(['endpoint' => 'fcm-other-device']);

    $this->withToken($this->token)->postJson('/api/v1/auth/logout', ['push_token' => 'fcm-mine'])->assertOk();

    expect($this->user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['fcm-other-device']);
});

it('supprime l’abonnement Web Push du navigateur à la déconnexion (push_endpoint)', function (): void {
    PushSubscription::factory()->for($this->user)->create(['kind' => 'webpush', 'endpoint' => WEBPUSH_ENDPOINT]);
    PushSubscription::factory()->for($this->user)->fcm()->create(['endpoint' => 'fcm-other-device']);

    $this->withToken($this->token)->postJson('/api/v1/auth/logout', ['push_endpoint' => WEBPUSH_ENDPOINT])->assertOk();

    expect($this->user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['fcm-other-device'])
        ->and(AuditLog::query()->where('action', 'push.unsubscribed')->count())->toBe(1);
});

it('ne notifie plus un jeton révoqué à distance (DELETE me/sessions/{id}) ni un appareil révoqué (DELETE me/devices/{uuid})', function (): void {
    $push = fakePush();
    $device = SyncDevice::factory()->for($this->user)->create();
    $lostToken = $this->user->createToken('Téléphone perdu');
    $lost = $lostToken->plainTextToken;
    $shared = $this->user->createToken('Tablette partagée')->plainTextToken;

    // Le téléphone perdu s'abonne avec son jeton ; la tablette partagée annonce son appareil.
    $this->withToken($lost)->postJson('/api/v1/me/push-subscriptions', ['kind' => 'fcm', 'token' => 'fcm-lost'])->assertCreated();
    app('auth')->forgetGuards();
    $this->withToken($shared)->withHeaders([DeviceRegistry::HEADER => $device->device_uuid])
        ->postJson('/api/v1/me/push-subscriptions', ['kind' => 'fcm', 'token' => 'fcm-shared'])->assertCreated();
    app('auth')->forgetGuards();
    $this->flushHeaders()->withToken($this->token)->postJson('/api/v1/me/push-subscriptions', ['kind' => 'fcm', 'token' => 'fcm-kept'])->assertCreated();

    expect(PushSubscription::query()->where('endpoint_hash', PushSubscription::hashEndpoint('fcm-lost'))->value('token_id'))->toBe((int) $lostToken->accessToken->getKey())
        ->and(PushSubscription::query()->where('endpoint_hash', PushSubscription::hashEndpoint('fcm-shared'))->value('sync_device_id'))->toBe($device->id);

    app('auth')->forgetGuards();
    $this->withToken($this->token)->deleteJson('/api/v1/me/sessions/token-'.$lostToken->accessToken->getKey())->assertOk();
    $this->withToken($this->token)->deleteJson("/api/v1/me/devices/{$device->device_uuid}")->assertNoContent();

    $this->user->refresh()->notify(new InvitationReceived(1, 'Mariam A.'));

    expect($this->user->pushSubscriptions()->get()->map(fn (PushSubscription $row): string => $row->endpoint)->all())->toBe(['fcm-kept'])
        ->and($push->calls)->toHaveCount(1);
});
