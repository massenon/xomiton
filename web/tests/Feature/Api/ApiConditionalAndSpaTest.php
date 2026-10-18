<?php

declare(strict_types=1);

use App\Http\Middleware\HandleConditionalGet;
use App\Models\DatabaseNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * P6-01 (lot api-me) : lectures conditionnelles génériques du groupe auth:sanctum (ETag, If-None-Match → 304,
 * Cache-Control private no-cache), réponses no-store laissées telles quelles, taille de page bornée, versionnement
 * /api/v1, Sanctum SPA (cookie de session sans jeton ; écriture sans X-XSRF-TOKEN → 419 au tu).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->withProfile([], ['theme' => 'light'])->create();
    $this->user->assignRole('membre');
    $this->token = $this->user->createToken('Pixel 7')->plainTextToken;
});

/** ValidateCsrfToken laisse tout passer pendant les tests : cette variante vérifie vraiment le jeton XSRF. */
function enforceCsrf(): void
{
    app()->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
}

it('pose un ETag privé sur GET me/settings et répond 304 sans corps à If-None-Match identique', function (): void {
    $this->getJson('/api/v1/me/settings')->assertUnauthorized()->assertHeaderMissing('ETag');

    $first = $this->withToken($this->token)->getJson('/api/v1/me/settings')->assertOk();
    $etag = (string) $first->headers->get('ETag');

    expect($etag)->toMatch('/^"[a-f0-9]{32}"$/')
        ->and($first->headers->get('Cache-Control'))->toContain('private')->toContain('no-cache')
        ->and($first->headers->get('Vary'))->toContain('Authorization');

    $replay = $this->withToken($this->token)->withHeaders(['If-None-Match' => $etag])->getJson('/api/v1/me/settings');

    $replay->assertStatus(304);
    expect($replay->getContent())->toBe('')
        ->and($replay->headers->get('ETag'))->toBe($etag);
});

it('reconnaît l’ETag suffixé par Apache mod_deflate (-gzip, -br) ou rendu faible (W/) : 304 sur cPanel', function (): void {
    $etag = trim((string) $this->withToken($this->token)->getJson('/api/v1/me/settings')->headers->get('ETag'), '"');

    foreach (["\"{$etag}-gzip\"", "\"{$etag}-br\"", "W/\"{$etag}\"", "\"autre\", \"{$etag}-gzip\""] as $header) {
        $this->withToken($this->token)->withHeaders(['If-None-Match' => $header])->getJson('/api/v1/me/settings')->assertStatus(304);
    }

    $this->withToken($this->token)->withHeaders(['If-None-Match' => "\"{$etag}x-gzip\""])->getJson('/api/v1/me/settings')->assertOk();

    expect(HandleConditionalGet::matches('"abc-gzip"', '"abc"'))->toBeTrue()
        ->and(HandleConditionalGet::matches('*', '"abc"'))->toBeTrue()
        ->and(HandleConditionalGet::matches('"abcd"', '"abc"'))->toBeFalse()
        ->and(HandleConditionalGet::matches(null, '"abc"'))->toBeFalse();
});

it('change d’ETag quand la ressource change : 200 avec le nouveau contenu', function (): void {
    $etag = (string) $this->withToken($this->token)->getJson('/api/v1/me/settings')->headers->get('ETag');

    $this->withToken($this->token)->patchJson('/api/v1/me/settings', ['theme' => 'dark'])->assertOk();

    $fresh = $this->withToken($this->token)->withHeaders(['If-None-Match' => $etag])->getJson('/api/v1/me/settings');

    $fresh->assertOk()->assertJsonPath('data.theme', 'dark');
    expect($fresh->headers->get('ETag'))->not->toBe($etag);
});

it('sert le 304 au polling de la cloche (unread-count)', function (): void {
    DatabaseNotification::factory()->forUser($this->user)->count(2)->create();

    $etag = (string) $this->withToken($this->token)->getJson('/api/v1/notifications/unread-count')
        ->assertOk()->assertJsonPath('data.unread', 2)->headers->get('ETag');

    $this->withToken($this->token)->withHeaders(['If-None-Match' => $etag])
        ->getJson('/api/v1/notifications/unread-count')->assertStatus(304);
});

it('ne touche ni aux réponses no-store, ni aux écritures, ni aux erreurs', function (): void {
    $mine = $this->withToken($this->token)->getJson('/api/v1/signalements/mine')->assertOk();
    expect($mine->headers->get('ETag'))->toBeNull()
        ->and($mine->headers->get('Cache-Control'))->toContain('no-store');

    $patch = $this->withToken($this->token)->withHeaders(['If-None-Match' => '*'])->patchJson('/api/v1/me/settings', ['theme' => 'light'])->assertOk();
    expect($patch->headers->get('ETag'))->toBeNull();
});

it('borne per_page au maximum configuré et retombe sur la valeur par défaut si invalide', function (): void {
    config(['xomiton.api.max_per_page' => 3]);
    DatabaseNotification::factory()->forUser($this->user)->count(5)->create();

    $this->withToken($this->token)->getJson('/api/v1/notifications?per_page=3')
        ->assertOk()->assertJsonPath('meta.per_page', 3)->assertJsonPath('meta.last_page', 2);

    $this->withToken($this->token)->getJson('/api/v1/notifications?per_page=500')
        ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors('per_page');

    Route::middleware(['api', 'auth:sanctum'])->get('api/v1/_test/per-page', fn (Illuminate\Http\Request $request) => response()->json([
        'per_page' => (new class extends App\Http\Controllers\Api\V1\ApiController
        {
            public function size(Illuminate\Http\Request $request): int
            {
                return $this->perPage($request, 2);
            }
        })->size($request),
    ]));

    $this->withToken($this->token)->getJson('/api/v1/_test/per-page?per_page=abc')->assertJsonPath('per_page', 2);
    $this->withToken($this->token)->getJson('/api/v1/_test/per-page?per_page=99')->assertJsonPath('per_page', 3);
    $this->withToken($this->token)->getJson('/api/v1/_test/per-page?per_page=0')->assertJsonPath('per_page', 1);
});

it('versionne toute l’API sous /api/v1 et pose `conditional` sur chaque route du groupe authentifié', function (): void {
    foreach (Route::getRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        if (! in_array('auth:sanctum', $middleware, true) || ! str_starts_with($route->uri(), 'api/')) {
            continue;
        }

        expect($route->uri())->toStartWith('api/v1/')
            ->and(in_array('conditional', $middleware, true))->toBeTrue("{$route->getName()} : lecture conditionnelle absente");
    }

    expect(HandleConditionalGet::etagFor('{"data":1}'))->toBe(HandleConditionalGet::etagFor('{"data":1}'))
        ->not->toBe(HandleConditionalGet::etagFor('{"data":2}'));
});

it('Sanctum SPA : la session web authentifie GET me sans jeton depuis le domaine du site', function (): void {
    $this->actingAs($this->user)
        ->withHeaders(['Referer' => 'http://localhost/fil'])
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);
});

it('Sanctum SPA : une écriture sans X-XSRF-TOKEN répond 419 session_expired (au tu), avec le jeton 200', function (): void {
    enforceCsrf();
    $stateful = ['Referer' => 'http://localhost/parametres', 'Accept' => 'application/json'];

    $this->actingAs($this->user)
        ->withHeaders($stateful)
        ->patchJson('/api/v1/me/settings', ['theme' => 'dark'])
        ->assertStatus(419)
        ->assertJsonPath('code', 'session_expired')
        ->assertJsonPath('message', __('errors.419.member.text'));

    $xsrf = encrypt(CookieValuePrefix::create('XSRF-TOKEN', app('encrypter')->getKey()).'jeton-spa', false);

    $this->actingAs($this->user)
        ->withSession(['_token' => 'jeton-spa'])
        ->withHeaders($stateful + ['X-XSRF-TOKEN' => $xsrf])
        ->patchJson('/api/v1/me/settings', ['theme' => 'dark'])
        ->assertOk()
        ->assertJsonPath('data.settings.theme', 'dark');
});
