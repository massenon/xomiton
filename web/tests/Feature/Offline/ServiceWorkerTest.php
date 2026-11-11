<?php

declare(strict_types=1);

use App\Application\Offline\ServiceWorkerScript;
use App\Models\Resource;
use App\Support\Settings;
use Tests\Feature\Authorization\Support\RoleUsers;

/*
 * Service worker (P5-10, ADR-26) : GET /sw.js (ServiceWorkerController, ServiceWorkerScript : ETag et 304, numéros
 * d'urgence de la page minimale, révision des ressources marquées) et annonce dans les gabarits (partials/offline) :
 * public et sobre l'enregistrent, l'espace connecté et le tunnel d'accès non, l'administration n'a pas la balise.
 */

beforeEach(function (): void {
    $this->swPath = tempnam(sys_get_temp_dir(), 'xm-sw-').'.js';
    file_put_contents($this->swPath, "(function(){/* workbox */})();\n");
    config(['xomiton.offline.service_worker.script' => $this->swPath]);
});

afterEach(function (): void {
    @unlink($this->swPath);
});

/**
 * @return array<string, mixed>
 */
function swConfig(string $content): array
{
    expect($content)->toStartWith('self.__XM_SW__=');
    $json = substr($content, strlen('self.__XM_SW__='), (int) strpos($content, ";\n") - strlen('self.__XM_SW__='));

    return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
}

it('sert /sw.js à la racine avec Service-Worker-Allowed « / », sans cache ni cookie', function (): void {
    $response = $this->get('/sw.js')->assertOk();

    $response->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
        ->assertHeader('Service-Worker-Allowed', '/')
        ->assertHeader('X-Robots-Tag', 'noindex');

    expect($response->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($response->headers->getCookies())->toBe([])
        ->and($response->getContent())->toEndWith("(function(){/* workbox */})();\n")
        ->and(route('service-worker', absolute: false))->toBe('/sw.js');
});

it('injecte la version, les pages gardées, le repli /urgences et les textes de la page hors connexion', function (): void {
    config(['xomiton.offline.service_worker.version' => '3']);

    $config = swConfig((string) $this->get('/sw.js')->getContent());

    expect($config)->toMatchArray([
        'version' => '3.0',
        'pages' => ['/urgences', '/sos', '/signaler', '/annuaire', '/ressources'],
        'fallback' => '/urgences',
        'timeout' => 6000,
        'texts' => ['title' => 'Hors connexion', 'text' => __('offline.sw.text'), 'numbers' => 'Numéros d’urgence'],
    ]);
});

it('liste les numéros d’urgence du réglage pour la page minimale hors connexion', function (): void {
    Settings::set('emergency_numbers', [['label' => 'Police secours', 'number' => '117'], ['label' => '', 'number' => '0']]);

    expect(swConfig((string) $this->get('/sw.js')->getContent())['numbers'])
        ->toBe([['label' => 'Police secours', 'number' => '117']]);
});

it('répond 304 sans corps quand le script n’a pas changé (ETag), 200 dès qu’il change', function (): void {
    $first = $this->get('/sw.js')->assertOk();
    $etag = (string) $first->headers->get('ETag');

    expect($etag)->toMatch('/^"[0-9a-f]{32}"$/');

    $this->withHeader('If-None-Match', $etag)->get('/sw.js')->assertStatus(304)->assertContent('')
        ->assertHeader('ETag', $etag);
    $this->withHeader('If-None-Match', 'W/'.$etag)->get('/sw.js')->assertStatus(304);

    app(ServiceWorkerScript::class)->bump();

    $this->withHeader('If-None-Match', $etag)->get('/sw.js')->assertOk();
});

it('précache pour tout le monde les ressources marquées par l’équipe, en chemins', function (): void {
    config(['xomiton.offline.library_enabled' => true]);
    $marked = Resource::factory()->pdf()->create(['is_offline_available' => true]);
    Resource::factory()->pdf()->create(['is_offline_available' => false]);

    $config = swConfig((string) $this->get('/sw.js')->getContent());

    expect($config['marked'])->toBe([[
        'url' => '/ressources/'.$marked->slug,
        'file' => '/ressources/'.$marked->slug.'/telecharger',
        'rev' => (string) $marked->updated_at?->getTimestamp(),
    ]]);

    config(['xomiton.offline.library_enabled' => false]);
    expect(swConfig((string) $this->get('/sw.js')->getContent())['marked'])->toBe([]);
});

it('change de version quand la tâche « Mise à jour du cache hors connexion » incrémente la révision', function (): void {
    $script = app(ServiceWorkerScript::class);
    $before = (string) $this->get('/sw.js')->getContent();

    expect($script->bump())->toBe(1)
        ->and($script->bump())->toBe(2)
        ->and(Settings::int(ServiceWorkerScript::REVISION_SETTING))->toBe(2);

    $after = (string) $this->get('/sw.js')->getContent();
    expect(swConfig($after)['version'])->toBe('1.2')
        ->and($after)->not->toBe($before);
});

it('répond 404 tant que le build n’a pas produit le service worker', function (): void {
    config(['xomiton.offline.service_worker.script' => sys_get_temp_dir().'/absent-xm-sw.js']);

    $this->get('/sw.js')->assertNotFound();
});

it('reste servi pendant la maintenance (parcours de crise hors connexion)', function (): void {
    Settings::set('maintenance', true);

    $this->get('/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/');
});

it('annonce le service worker dans les gabarits public et sobre seulement', function (): void {
    $public = (string) $this->get('/')->assertOk()->getContent();
    $plain = (string) $this->get('/signaler')->assertOk()->getContent();
    $auth = (string) $this->get('/connexion')->assertOk()->getContent();

    expect($public)->toContain('<meta name="xm-offline" content="/sw.js"')
        ->and($plain)->toContain('<meta name="xm-offline" content="/sw.js"')
        ->and($auth)->toContain('<meta name="xm-offline" content=""')
        ->and($plain)->toContain('report_sent_title')
        ->and($plain)->toContain('&quot;report_url&quot;:&quot;/signaler&quot;');
});

it('n’annonce rien dans l’administration', function (): void {
    RoleUsers::seedRoles();

    $html = (string) $this->actingAs(RoleUsers::make('admin'))->get('/admin')->assertOk()->getContent();

    expect($html)->not->toContain('name="xm-offline"');
});
