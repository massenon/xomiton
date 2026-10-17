<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Lot « securite-http » (P2-08) : limiteurs nommés de AppServiceProvider::registerRateLimiters().
 * Chaque limiteur est éprouvé sur une route de test portant throttle:<nom>, indépendamment des contrôleurs
 * des autres lots ; la dernière épreuve vise la vraie route POST /connexion dès qu'elle existe.
 */

/**
 * Enregistre une route POST éphémère protégée par le limiteur donné.
 */
function throttledRoute(string $limiter, string $group = 'web'): string
{
    $uri = '/_test/'.$limiter;

    Route::post($uri, static fn () => response()->json(['data' => ['ok' => true]]))
        ->middleware([$group, 'throttle:'.$limiter])
        ->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    return $uri;
}

function hasPostRouteNamed(string $name): bool
{
    foreach (Route::getRoutes()->get('POST') as $route) {
        if ($route->getName() === $name) {
            return true;
        }
    }

    return false;
}

it('limite la connexion à 5 tentatives par minute puis répond 429 à la 6e, par identifiant et IP', function (): void {
    $uri = throttledRoute('login');
    $payload = ['identifier' => 'Membre@xomiton.bj', 'password' => 'mauvais'];

    foreach (range(1, 5) as $i) {
        $this->postJson($uri, $payload)->assertOk()->assertHeader('X-RateLimit-Remaining', (string) (5 - $i));
    }

    $blocked = $this->postJson($uri, $payload)->assertStatus(429);
    $blocked->assertHeader('Retry-After')->assertJsonPath('message', fn (string $m): bool => str_starts_with($m, 'Trop de tentatives'));

    // La casse de l'identifiant ne contourne pas le compteur…
    $this->postJson($uri, ['identifier' => 'membre@xomiton.bj', 'password' => 'x'])->assertStatus(429);

    // … mais un autre identifiant ou une autre IP ont leur propre compteur.
    $this->postJson($uri, ['identifier' => 'autre@xomiton.bj', 'password' => 'x'])->assertOk();
    $this->postJson($uri, $payload, ['REMOTE_ADDR' => '203.0.113.9'])->assertOk();
});

it('limite les envois de code OTP à 5 par heure et par adresse e-mail', function (): void {
    $uri = throttledRoute('otp');

    foreach (range(1, 5) as $i) {
        $this->postJson($uri, ['email' => 'membre@xomiton.bj'])->assertOk();
    }

    $this->postJson($uri, ['email' => 'MEMBRE@xomiton.bj'])->assertStatus(429)
        ->assertJsonPath('message', fn (string $m): bool => str_starts_with($m, 'Trop de codes demandés'));

    $this->postJson($uri, ['email' => 'pair@xomiton.bj'])->assertOk();
});

it('limite le signalement anonyme à 10 par heure et par IP', function (): void {
    $uri = throttledRoute('report');

    foreach (range(1, 10) as $i) {
        $this->postJson($uri)->assertOk();
    }

    $this->postJson($uri)->assertStatus(429);
    $this->postJson($uri, [], ['REMOTE_ADDR' => '198.51.100.7'])->assertOk();
});

it('limite le suivi anonyme à 20 consultations par heure et par IP', function (): void {
    $uri = throttledRoute('tracking');

    foreach (range(1, 20) as $i) {
        $this->postJson($uri)->assertOk();
    }

    $this->postJson($uri)->assertStatus(429);
});

it('n’accepte qu’une inscription newsletter toutes les 20 secondes par session', function (): void {
    $uri = throttledRoute('newsletter');
    $sessionId = Str::random(40);

    // Le navigateur renvoie le cookie de session : la même session est reconnue d'une requête à l'autre.
    $this->withCredentials()->withCookie(config('session.cookie'), $sessionId);

    $this->postJson($uri)->assertOk();
    $this->postJson($uri)->assertStatus(429)->assertHeader('Retry-After');

    // Une autre session n'est pas pénalisée.
    $this->withCookie(config('session.cookie'), Str::random(40))->postJson($uri)->assertOk();

    // La fenêtre de 20 s écoulée, la même session peut recommencer.
    $this->travel(21)->seconds();

    $this->withCookie(config('session.cookie'), $sessionId)->postJson($uri)->assertOk();
});

it('compte la newsletter par IP quand le client ne renvoie pas de cookie de session', function (): void {
    $uri = throttledRoute('newsletter');

    // Sans cookie, chaque requête ouvrirait une session neuve : la limite se replie sur l'adresse IP.
    $this->postJson($uri)->assertOk();
    $this->postJson($uri)->assertStatus(429);

    $this->postJson($uri, [], ['REMOTE_ADDR' => '203.0.113.42'])->assertOk();
});

it('limite l’API à 60 requêtes par minute par IP ou par utilisateur·rice', function (): void {
    $uri = throttledRoute('api', 'api');

    foreach (range(1, 60) as $i) {
        $this->postJson($uri)->assertOk();
    }

    $this->postJson($uri)->assertStatus(429)->assertJsonStructure(['message']);
});

it('applique le limiteur api à tout le groupe /api/v1 via bootstrap/app.php', function (): void {
    foreach (range(1, 60) as $i) {
        $this->getJson('/api/v1/health')->assertOk()->assertHeader('X-RateLimit-Limit', '60');
    }

    $this->getJson('/api/v1/health')->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('message', fn (string $m): bool => str_starts_with($m, 'Trop de tentatives'));

    // Une autre adresse a son propre compteur.
    $this->getJson('/api/v1/health', ['REMOTE_ADDR' => '198.51.100.20'])->assertOk();
});

it('lit les seuils dans la configuration au moment de la requête', function (): void {
    config()->set('xomiton.rate_limits.login_per_minute', 2);
    $uri = throttledRoute('login');
    $payload = ['identifier' => 'membre@xomiton.bj', 'password' => 'x'];

    $this->postJson($uri, $payload)->assertOk();
    $this->postJson($uri, $payload)->assertOk();
    $this->postJson($uri, $payload)->assertStatus(429);
});

it('ne plante pas quand le champ e-mail du limiteur otp n’est pas une chaîne', function (): void {
    $uri = throttledRoute('otp');

    // Un tableau ou un champ vide : le compteur se replie sur l'IP sans erreur 500.
    $this->postJson($uri, ['email' => ['x' => 'y']])->assertOk();
    $this->postJson($uri, ['email' => ''])->assertOk();
    $this->postJson($uri, ['identifier' => ['a', 'b']])->assertOk();
});

it('répond 429 à la 6e tentative sur POST /connexion', function (): void {
    $payload = ['identifier' => 'membre@xomiton.bj', 'email' => 'membre@xomiton.bj', 'password' => 'MauvaisMotDePasse1'];

    foreach (range(1, 5) as $i) {
        $this->post(route('connexion.store'), $payload)->assertStatus(302);
    }

    $this->post(route('connexion.store'), $payload)->assertStatus(429);
})->skip(fn (): bool => ! hasPostRouteNamed('connexion.store'), 'La route POST connexion.store est fournie par le lot authentification web.');
