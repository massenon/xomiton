<?php

declare(strict_types=1);

use App\Domain\Notifications\PushMessage;
use App\Infrastructure\Notifications\FirebaseFcmSender;
use App\Infrastructure\Notifications\GoogleAccessToken;
use App\Infrastructure\Notifications\NotificationDrivers;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * Pilote FCM HTTP v1 (Q7) : compte de service → jeton OAuth2 signé RS256 et mis en cache fichier, envoi par jeton
 * d'appareil, jetons invalides → gone, priorité haute pour les alertes, charge utile discrète. Aucun appel réseau réel.
 */

function fcmServiceAccount(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $path = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, json_encode(['type' => 'service_account', 'project_id' => 'xomiton-test', 'client_email' => 'push@xomiton-test.iam.gserviceaccount.com', 'private_key' => $pem]));

    return $path;
}

function fcmMessage(string $category = 'messages'): PushMessage
{
    return new PushMessage('Xomiton', 'Nouveau message.', '/messages/4', ['type' => 'message', 'id' => 4, 'url' => '/messages/4', 'category' => $category]);
}

beforeEach(function (): void {
    $this->credentials = fcmServiceAccount();
    config(['xomiton.notifications.push.fcm.credentials' => $this->credentials, 'xomiton.notifications.push.fcm.driver' => 'fcm']);
    Cache::store('file')->forget('fcm.google_access_token');
    Http::preventStrayRequests();
});

afterEach(fn () => @unlink($this->credentials));

it('résout le pilote fcm et signe le JWT du compte de service', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/x/messages/1']),
    ]);

    $sender = NotificationDrivers::fcm($this->app);
    expect($sender)->toBeInstanceOf(FirebaseFcmSender::class);

    $result = $sender->send(new PushSubscription(['kind' => 'fcm', 'endpoint' => 'device-token-1']), fcmMessage());

    expect($result->ok)->toBeTrue();
    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'oauth2')) {
            return false;
        }
        [$header, $claims] = array_map(fn ($p) => json_decode(base64_decode(strtr($p, '-_', '+/')), true), array_slice(explode('.', $request['assertion']), 0, 2));

        return $header['alg'] === 'RS256' && $claims['scope'] === GoogleAccessToken::SCOPE && $claims['iss'] === 'push@xomiton-test.iam.gserviceaccount.com';
    });
});

it('envoie une charge utile discrète et met le jeton OAuth2 en cache', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response([]),
    ]);
    $sender = new FirebaseFcmSender;
    $subscription = new PushSubscription(['kind' => 'fcm', 'endpoint' => 'device-token-1']);

    $sender->send($subscription, fcmMessage());
    $sender->send($subscription, fcmMessage());

    Http::assertSentCount(3); // un seul échange OAuth2 pour deux envois
    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), '/v1/projects/xomiton-test/messages:send')) {
            return false;
        }
        $m = $request['message'];

        return $request->hasHeader('Authorization', 'Bearer tok-1')
            && $m['token'] === 'device-token-1'
            && $m['data'] === ['type' => 'message', 'id' => '4', 'category' => 'messages', 'url' => '/messages/4']
            && $m['android']['priority'] === 'NORMAL';
    });
});

it('donne la priorité haute et le canal SOS aux alertes', function (): void {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]), 'fcm.googleapis.com/*' => Http::response([])]);

    (new FirebaseFcmSender)->send(new PushSubscription(['kind' => 'fcm', 'endpoint' => 'd']), fcmMessage('sos'));

    Http::assertSent(fn ($r): bool => isset($r['message']) && $r['message']['android']['priority'] === 'HIGH'
        && $r['message']['android']['notification']['channel_id'] === 'sos_alerts'
        && $r['message']['apns']['headers']['apns-priority'] === '10');
});

it('signale gone pour un jeton désinscrit ou invalide, failed pour le reste', function (): void {
    $sender = new FirebaseFcmSender;

    expect($sender->interpretResponse(404, json_encode(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]]))->gone)->toBeTrue()
        ->and($sender->interpretResponse(400, json_encode(['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'The registration token is not a valid FCM registration token']]))->gone)->toBeTrue()
        ->and($sender->interpretResponse(400, json_encode(['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'Invalid data payload']]))->gone)->toBeFalse()
        ->and($sender->interpretResponse(503, '{}'))->toMatchObject(['ok' => false, 'gone' => false])
        ->and($sender->interpretTransportError('timeout')->ok)->toBeFalse();
});

it('oublie le jeton OAuth2 sur un 401 pour en redemander un', function (): void {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'old', 'expires_in' => 3600]), 'fcm.googleapis.com/*' => Http::response([], 401)]);
    $sender = new FirebaseFcmSender;

    $result = $sender->send(new PushSubscription(['kind' => 'fcm', 'endpoint' => 'd']), fcmMessage());

    expect($result->ok)->toBeFalse()->and(Cache::store('file')->get('fcm.google_access_token'))->toBeNull();
});

it('échoue proprement sans compte de service lisible', function (): void {
    config(['xomiton.notifications.push.fcm.credentials' => '/nonexistent.json']);

    $result = (new FirebaseFcmSender)->send(new PushSubscription(['kind' => 'fcm', 'endpoint' => 'd']), fcmMessage());

    expect($result->ok)->toBeFalse();
    Http::assertNothingSent();
});

it('garde le pilote log par défaut', function (): void {
    expect(config('xomiton.notifications.push.fcm.driver'))->toBe('fcm'); // forcé par le test
    expect((new ReflectionClassConstant(NotificationDrivers::class, 'LOG'))->getValue())->toBe('log');
    expect(file_get_contents(base_path('.env.example')))->toContain('FCM_DRIVER=log');
});
