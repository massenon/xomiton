<?php

declare(strict_types=1);

use App\Domain\Notifications\PhoneNumber;
use App\Infrastructure\Notifications\LogSmsSender;
use App\Infrastructure\Notifications\NotificationDrivers;
use App\Infrastructure\Notifications\TwilioSmsSender;
use App\Infrastructure\Notifications\VonageSmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/* Pilotes SMS (Q7) : log par défaut, Vonage et Twilio par REST (Http::fake, aucun appel réel), E.164, journal sans contenu. */

beforeEach(function (): void {
    Http::preventStrayRequests();
    config([
        'xomiton.sms.sender' => 'Xomiton',
        'xomiton.sms.vonage.api_key' => 'vk', 'xomiton.sms.vonage.api_secret' => 'vs',
        'xomiton.sms.twilio.account_sid' => 'ACtest', 'xomiton.sms.twilio.auth_token' => 'tt',
    ]);
});

it('normalise en E.164 avec +229 par défaut', function (string $raw, ?string $expected): void {
    expect(PhoneNumber::e164($raw))->toBe($expected);
})->with([
    ['+229 01 97 38 88 47', '+2290197388847'],
    ['0197388847', '+2290197388847'],
    ['00229 97 38 88 47', '+22997388847'],
    ['+33 6 12 34 56 78', '+33612345678'],
    ['abc', null],
    ['+12', null],
]);

it('résout le pilote selon xomiton.sms.driver et retombe sur log', function (string $driver, string $class): void {
    config(['xomiton.sms.driver' => $driver]);

    expect(NotificationDrivers::sms($this->app))->toBeInstanceOf($class);
})->with([['log', LogSmsSender::class], ['vonage', VonageSmsSender::class], ['twilio', TwilioSmsSender::class], ['inconnu', LogSmsSender::class]]);

it('envoie par Vonage sans journaliser le texte ni le numéro', function (): void {
    Log::spy();
    Http::fake(['rest.nexmo.com/*' => Http::response(['messages' => [['status' => '0', 'message-id' => 'abc']]])]);

    $result = (new VonageSmsSender)->send('0197388847', 'Code SOS 4242 à Cotonou');

    expect($result->ok)->toBeTrue();
    Http::assertSent(fn ($r): bool => $r['to'] === '2290197388847' && $r['from'] === 'Xomiton' && $r['text'] === 'Code SOS 4242 à Cotonou' && $r['api_key'] === 'vk');
    Log::shouldHaveReceived('info')->withArgs(fn (string $m, array $c = []): bool => $m === 'sms.accepted' && ! str_contains(json_encode($c), '4242') && ! str_contains(json_encode($c), '97388847'));
});

it('rapporte les erreurs Vonage comme échec rejouable', function (): void {
    Http::fake(['rest.nexmo.com/*' => Http::response(['messages' => [['status' => '9', 'error-text' => 'Quota Exceeded']]])]);

    $result = (new VonageSmsSender)->send('+22997388847', 'x');

    expect($result)->toMatchObject(['ok' => false, 'gone' => false])->and($result->error)->toContain('Quota');
});

it('envoie par Twilio avec authentification Basic et service de messagerie', function (): void {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
    config(['xomiton.sms.twilio.messaging_service_sid' => 'MG123']);

    $result = (new TwilioSmsSender)->send('+22997388847', 'Bonjour');

    expect($result->ok)->toBeTrue();
    Http::assertSent(function ($r): bool {
        parse_str($r->body(), $form);

        return str_contains($r->url(), '/Accounts/ACtest/Messages.json')
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('ACtest:tt'))
            && $form['To'] === '+22997388847' && $form['MessagingServiceSid'] === 'MG123' && ! isset($form['From']);
    });
});

it('utilise l’expéditeur Twilio sans service de messagerie et signale les erreurs', function (): void {
    Http::fake(['api.twilio.com/*' => Http::response(['code' => 21211, 'message' => 'Invalid To'], 400)]);

    $result = (new TwilioSmsSender)->send('+22997388847', 'Bonjour');

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('21211');
    Http::assertSent(function ($r): bool {
        parse_str($r->body(), $form);

        return $form['From'] === 'Xomiton';
    });
});

it('échoue sans appel pour un numéro invalide ou des identifiants absents', function (): void {
    Http::fake();

    expect((new VonageSmsSender)->send('abc', 'x')->ok)->toBeFalse();
    config(['xomiton.sms.twilio.auth_token' => null]);
    expect((new TwilioSmsSender)->send('+22997388847', 'x')->ok)->toBeFalse();
    Http::assertNothingSent();
});

it('traite un délai dépassé comme un échec', function (): void {
    Http::fake(['rest.nexmo.com/*' => fn () => throw new Illuminate\Http\Client\ConnectionException('timeout')]);

    expect((new VonageSmsSender)->send('+22997388847', 'x'))->toMatchObject(['ok' => false, 'gone' => false]);
});
