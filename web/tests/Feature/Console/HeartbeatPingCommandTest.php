<?php

declare(strict_types=1);

use App\Console\Commands\Alerts\EscalateAlertsCommand;
use App\Console\Commands\Alerts\RetryNotificationsCommand;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * `heartbeat:ping` (P3-07, D7) : GET sur xomiton.heartbeat.url, délai 5 s, silencieux sans URL.
 */

beforeEach(fn () => config(['xomiton.heartbeat.require_markers' => false]));

it('appelle l’URL de supervision configurée', function (): void {
    config(['xomiton.heartbeat.url' => 'https://hc-ping.test/xomiton']);
    Http::fake(['hc-ping.test/*' => Http::response('OK', 200)]);

    $this->artisan('heartbeat:ping')
        ->expectsOutputToContain('Signal de vie envoyé')
        ->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hc-ping.test/xomiton' && $request->method() === 'GET');
});

it('reste silencieuse et n’émet rien sans URL', function (): void {
    config(['xomiton.heartbeat.url' => null]);
    Http::fake();

    $this->artisan('heartbeat:ping')
        ->doesntExpectOutput('Signal de vie envoyé.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('signale un refus de la supervision ou une erreur réseau', function (): void {
    config(['xomiton.heartbeat.url' => 'https://hc-ping.test/xomiton']);
    Http::fake(['hc-ping.test/*' => Http::response('', 503)]);

    $this->artisan('heartbeat:ping')
        ->expectsOutputToContain('HTTP 503')
        ->assertFailed();

    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $this->artisan('heartbeat:ping')
        ->expectsOutputToContain('Signal de vie non délivré')
        ->assertFailed();
});

it('retient le signal de vie tant que les marqueurs de succès d’escalade et de rejeu sont absents', function (): void {
    config(['xomiton.heartbeat.url' => 'https://hc.example/ping', 'xomiton.heartbeat.require_markers' => true]);
    Http::fake();

    $this->artisan('heartbeat:ping')->assertFailed();
    Http::assertNothingSent();

    Cache::put(EscalateAlertsCommand::SUCCESS_MARKER, now()->toIso8601String(), 180);
    $this->artisan('heartbeat:ping')->assertFailed();
    Http::assertNothingSent();

    Cache::put(RetryNotificationsCommand::SUCCESS_MARKER, now()->toIso8601String(), 180);
    $this->artisan('heartbeat:ping')->assertSuccessful();
    Http::assertSentCount(1);
});
