<?php

declare(strict_types=1);

use App\Http\Middleware\CacheableGuestPage;

/*
 * Lot IOS-09 : fichiers de liens universels publics, sans redirection, sans session, sans altération par le cache invité.
 */

it('sert l’AASA en JSON, sans redirection ni cookie, avec l’identifiant d’application', function (): void {
    config(['xomiton.universal_links.apple_team_id' => 'ABCDE12345']);

    $response = $this->get('/.well-known/apple-app-site-association');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('application/json')
        ->and($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Location'))->toBeNull()
        ->and((string) $response->headers->get('Vary'))->not->toContain('Cookie');
    $response->assertJsonPath('applinks.details.0.appIDs.0', 'ABCDE12345.app.xomiton.xomiton')
        ->assertJsonPath('applinks.details.0.components.0./', '/invitation/*')
        ->assertJsonPath('applinks.details.0.components.1./', '/signup/*')
        ->assertJsonPath('applinks.details.0.components.2./', '/alerte/*');
    expect($response->baseResponse->headers->has('ETag'))->toBeFalse();
});

it('reste valide avec des identifiants vides', function (): void {
    config(['xomiton.universal_links.apple_team_id' => '', 'xomiton.universal_links.android_sha256_fingerprints' => []]);

    $this->get('/.well-known/apple-app-site-association')->assertOk()->assertJsonPath('applinks.details.0.appIDs', []);
    $android = $this->get('/.well-known/assetlinks.json');
    $android->assertOk();
    expect($android->json())->toBe([])
        ->and($android->headers->get('Content-Type'))->toStartWith('application/json');
});

it('sert assetlinks.json avec le paquet et les empreintes configurées', function (): void {
    $fp = str_repeat('AA:', 31).'AA';
    config(['xomiton.universal_links.android_sha256_fingerprints' => [$fp]]);

    $this->get('/.well-known/assetlinks.json')->assertOk()
        ->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls')
        ->assertJsonPath('0.target.namespace', 'android_app')
        ->assertJsonPath('0.target.package_name', 'app.xomiton.xomiton')
        ->assertJsonPath('0.target.sha256_cert_fingerprints.0', $fp);
});

it('lit les identifiants de l’environnement (liste d’empreintes séparée par des virgules)', function (): void {
    putenv('ANDROID_SHA256_FINGERPRINTS=AA:BB, CC:DD');
    $_ENV['ANDROID_SHA256_FINGERPRINTS'] = $_SERVER['ANDROID_SHA256_FINGERPRINTS'] = 'AA:BB, CC:DD';
    $config = require config_path('xomiton.php');
    putenv('ANDROID_SHA256_FINGERPRINTS');
    unset($_ENV['ANDROID_SHA256_FINGERPRINTS'], $_SERVER['ANDROID_SHA256_FINGERPRINTS']);

    expect($config['universal_links']['android_sha256_fingerprints'])->toBe(['AA:BB', 'CC:DD']);
});

it('n’est pas touché par le middleware de cache invité ni par la session', function (): void {
    $route = app('router')->getRoutes()->getByName('well-known.aasa');

    expect($route->gatherMiddleware())->not->toContain('web')->not->toContain('guest.page')->not->toContain(CacheableGuestPage::class);
    $response = $this->get('/.well-known/apple-app-site-association');
    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and(request()->attributes->get(CacheableGuestPage::ATTRIBUTE))->toBeNull();
});

it('répond sans compte (aucun groupe web, donc hors mode maintenance)', function (): void {
    $this->get('/.well-known/assetlinks.json')->assertOk();
    $this->get('/.well-known/apple-app-site-association')->assertOk();
});
