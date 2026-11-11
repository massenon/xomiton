<?php

declare(strict_types=1);

use App\Domain\Notifications\PushMessage;
use App\Infrastructure\Notifications\FirebaseFcmSender;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * Lot IOS-05 : charge FCM des appareils iOS (bloc `notification` neutre + apns) comparée à celle d'Android.
 */

function iosServiceAccount(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $path = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, json_encode(['type' => 'service_account', 'project_id' => 'xomiton-test', 'client_email' => 'p@x.iam.gserviceaccount.com', 'private_key' => $pem]));

    return $path;
}

function iosMessage(string $type, string $category = 'alertes', bool $masked = false, bool $silent = false): PushMessage
{
    return new PushMessage('Récit secret', 'Détail sensible : SOS VBG admin', '/x/1', ['type' => $type, 'id' => 9, 'url' => '/x/1', 'category' => $category], silent: $silent, masked: $masked);
}

function sentMessage(string $platform, PushMessage $message): array
{
    $sub = new PushSubscription(['kind' => 'fcm', 'endpoint' => 'tok', 'platform' => $platform]);
    $spec = (new FirebaseFcmSender)->describeRequest($sub, $message);

    return $spec->json['message'];
}

beforeEach(function (): void {
    $this->credentials = iosServiceAccount();
    config(['xomiton.notifications.push.fcm.credentials' => $this->credentials, 'xomiton.notifications.push.fcm.driver' => 'fcm']);
    Cache::store('file')->put('fcm.google_access_token', 'tok-1', 600);
    Http::preventStrayRequests();
});

afterEach(fn () => @unlink($this->credentials));

it('envoie aux iPhone un bloc notification neutre, time-sensitive pour astreinte et SOS', function (string $type, string $category): void {
    $m = sentMessage('ios', iosMessage($type, $category));

    expect($m['notification']['title'])->toBe('Xomiton')
        ->and(json_encode([$m['notification'], $m['apns']['payload']]))->not->toMatch('/SOS|VBG|admin|secret|sensible/i')
        ->and($m['apns']['headers'])->toBe(['apns-priority' => '10', 'apns-push-type' => 'alert'])
        ->and($m['apns']['payload']['aps']['interruption-level'])->toBe('time-sensitive')
        ->and($m['apns']['payload']['aps']['mutable-content'])->toBe(1)
        ->and($m['apns']['payload']['aps']['thread-id'])->toStartWith('xomiton-')
        ->and($m['data']['type'])->toBe($type);
})->with([['duty_sos', 'sos'], ['duty_alert', 'alertes'], ['alert_routed', 'alertes'], ['sos', 'sos']]);

it('garde le niveau active pour les types communautaires et un corps générique par type', function (string $type, string $body): void {
    $m = sentMessage('ios', iosMessage($type, 'messages'));

    expect($m['apns']['payload']['aps']['interruption-level'])->toBe('active')
        ->and($m['notification']['body'])->toBe($body);
})->with([['message', 'Nouveau message'], ['peer_pending', 'Une demande attend ton avis'], ['content_reported', 'Un contenu attend un examen'], ['group', 'Nouvelle notification']]);

it('ne montre que le texte le plus nu en mode discret', function (): void {
    $m = sentMessage('ios', iosMessage('message', 'messages', masked: true));

    expect($m['notification'])->toBe(['title' => 'Xomiton', 'body' => 'Nouvelle notification']);
});

it('réveille iOS en arrière-plan pour sync et rules (content-available, priorité 5)', function (string $type): void {
    $m = sentMessage('ios', iosMessage($type, 'systeme', silent: true));

    expect($m)->not->toHaveKey('notification')
        ->and($m['apns']['headers'])->toBe(['apns-priority' => '5', 'apns-push-type' => 'background'])
        ->and($m['apns']['payload']['aps'])->toBe(['content-available' => 1]);
})->with(['sync', 'rules']);

it('ne change rien pour Android et les appareils de plateforme inconnue', function (?string $platform): void {
    $m = sentMessage((string) $platform, iosMessage('duty_sos', 'sos'));

    expect($m['notification']['title'])->toBe('Récit secret')
        ->and($m['android']['notification']['channel_id'])->toBe('duty')
        ->and($m['apns'])->toBe(['headers' => ['apns-priority' => '10']]);
})->with(['android', null]);

it('enregistre la plateforme déclarée ou déduite de l’appareil', function (): void {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('iPhone')->plainTextToken];

    $this->postJson('/api/v1/me/push-subscriptions', ['kind' => 'fcm', 'token' => 'tok-ios', 'platform' => 'ios'], $headers)
        ->assertSuccessful()->assertJsonPath('data.platform', 'ios');

    $device = SyncDevice::factory()->create(['user_id' => $user->id, 'platform' => 'iOS 17.4']);
    $this->postJson('/api/v1/me/push-subscriptions', ['kind' => 'fcm', 'token' => 'tok-2'], $headers + ['X-Device-Id' => $device->device_uuid])
        ->assertSuccessful();

    expect(PushSubscription::query()->where('user_id', $user->id)->orderBy('id')->pluck('platform')->all())->toBe(['ios', 'ios']);

    $this->postJson('/api/v1/me/push-subscriptions', ['kind' => 'fcm', 'token' => 'tok-3', 'platform' => 'windows'], $headers)->assertUnprocessable();
});
