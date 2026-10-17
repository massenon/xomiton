<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * Lot « securite-http » (P2-08, P2-13) : en-têtes posés par App\Http\Middleware\SecurityHeaders.
 */

/**
 * Extrait le nonce de l'en-tête Content-Security-Policy d'une réponse.
 */
function cspNonceOf(TestResponse $response): string
{
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect(preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $matches))->toBe(1);

    return $matches[1];
}

it('pose les en-têtes de sécurité sur la page d’accueil', function (): void {
    $response = $this->get('/')->assertOk();

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()')
        ->assertHeaderMissing('X-Powered-By');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self' 'nonce-")
        ->toContain("'unsafe-eval'")
        ->toContain("style-src 'self' 'unsafe-inline'")
        ->toContain("img-src 'self' data: blob:")
        ->toContain("connect-src 'self'")
        ->toContain("font-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'");

    // P2-13 : jamais d'« unsafe-inline » sur les scripts, le nonce s'y substitue.
    preg_match('/script-src ([^;]+)/', $csp, $scriptSrc);
    expect($scriptSrc[1])->not->toContain("'unsafe-inline'");
});

it('génère un nonce différent à chaque requête et le reporte sur les scripts du gabarit', function (): void {
    $first = $this->get('/')->assertOk();
    $second = $this->get('/')->assertOk();

    $nonceA = cspNonceOf($first);
    $nonceB = cspNonceOf($second);

    expect($nonceA)->not->toBe($nonceB)
        ->and(strlen($nonceA))->toBeGreaterThanOrEqual(32);

    // Le script en ligne du thème (partials/head) et les balises @vite portent le nonce de la requête.
    $first->assertSee('nonce="'.$nonceA.'"', false);
    $second->assertSee('nonce="'.$nonceB.'"', false);
    $first->assertDontSee('nonce="'.$nonceB.'"', false);
});

it('n’autorise la géolocalisation que sur les pages SOS et signalement', function (): void {
    $this->get('/sos')->assertOk()
        ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');

    $this->get('/signaler')->assertOk()
        ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');

    $this->get('/')->assertOk()
        ->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

    $this->get('/connexion')->assertOk()
        ->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
});

it('pose les en-têtes aussi sur l’API et la sonde /up', function (): void {
    $this->getJson('/api/v1/health')->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get('/up')->assertOk()->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('n’envoie pas HSTS hors production ni sur une requête non sécurisée', function (): void {
    $this->get('/')->assertOk()->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
});

it('envoie HSTS un an avec sous-domaines en production sur une requête sécurisée', function (): void {
    $this->app['env'] = 'production';

    try {
        $this->get('https://localhost/sos')->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $this->get('http://localhost/sos')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    } finally {
        $this->app['env'] = 'testing';
    }
});

it('utilise des cookies de session SameSite lax et HttpOnly', function (): void {
    expect(config('session.same_site'))->toBe('lax')
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.secure'))->toBeFalse();
});

it('n’écrase pas un en-tête déjà posé par la réponse', function (): void {
    Route::get('/_test/csp-souple', static fn () => response('ok')
        ->header('Content-Security-Policy', "default-src 'self'; frame-src 'self'")
        ->header('X-Frame-Options', 'SAMEORIGIN'))
        ->middleware('web');

    $this->get('/_test/csp-souple')->assertOk()
        ->assertHeader('Content-Security-Policy', "default-src 'self'; frame-src 'self'")
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        // Les autres en-têtes restent posés.
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
