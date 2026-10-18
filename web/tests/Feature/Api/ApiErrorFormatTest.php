<?php

declare(strict_types=1);

use App\Domain\Shared\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
 * Format d'erreur de /api/v1 (P5-09, SOFTWARE_ARCHITECTURE.md §4, docs/API.md §1.2) : `{ message, code, errors? }`
 * pour chaque statut (401, 403, 404, 409, 419, 422, 429, 500 hors débogage) et pour les exceptions métier
 * (App\Domain\Shared\Exceptions\DomainException) ; messages français, jamais le message brut de l'exception.
 */

final class TestStockConflict extends DomainException
{
    public function errorCode(): string
    {
        return 'test_conflict';
    }

    public function status(): int
    {
        return 409;
    }

    public function errors(): array
    {
        return ['version' => [$this->userMessage()]];
    }

    public function extra(): array
    {
        return ['server_version' => 3];
    }

    public function headers(): array
    {
        return ['X-Test' => 'oui'];
    }
}

final class TestDefaultDomainError extends DomainException
{
    public function errorCode(): string
    {
        return 'test_default';
    }
}

/**
 * Vérifie le statut, le code et les seules clés autorisées du corps d'erreur.
 *
 * @param  list<string>  $keys
 */
function expectApiError(TestResponse $response, int $status, string $code, array $keys = ['message', 'code']): void
{
    $response->assertStatus($status)->assertHeader('Content-Type', 'application/json');

    expect($response->json('code'))->toBe($code)
        ->and($response->json('message'))->toBeString()->not->toBeEmpty()
        ->and(array_keys($response->json()))->toEqualCanonicalizing($keys);
}

beforeEach(function (): void {
    Route::middleware('api')->prefix('api/v1/_test')->group(function (): void {
        Route::get('forbidden', static fn () => throw new AuthorizationException('Raw policy message'));
        Route::get('conflict', static fn () => abort(409, 'Raw conflict'));
        Route::get('session', static fn () => abort(419));
        Route::get('member-session', static fn () => abort(419))->middleware('auth:sanctum');
        Route::get('busy', static fn () => abort(429));
        Route::get('wrong-method', static fn () => abort(405));
        Route::get('bad', static fn () => abort(400, 'Raw bad request'));
        Route::get('gone', static fn () => abort(410));
        Route::get('teapot', static fn () => abort(418));
        Route::post('validate', static fn () => request()->validate(['pseudo' => ['required', 'string']]));
        Route::post('validate-many', static fn () => request()->validate(['pseudo' => ['required'], 'email' => ['required'], 'zone' => ['required']]));
        Route::get('domain', static fn () => throw new TestStockConflict('errors.api.conflict'));
        Route::get('domain-default', static fn () => throw new TestDefaultDomainError('errors.api.validation'));
        Route::get('crash', static fn () => throw new RuntimeException('secret stack detail'));
        Route::post('limited', static fn () => response()->json(['data' => ['ok' => true]]))->middleware('throttle:report');
    });
});

it('répond 401 unauthenticated sans jeton', function (): void {
    $response = $this->getJson('/api/v1/me');

    expectApiError($response, 401, 'unauthenticated');
    expect($response->json('message'))->toBe(__('auth.api.unauthenticated'));
});

it('répond 401 même sans en-tête Accept JSON', function (): void {
    expectApiError($this->get('/api/v1/me'), 401, 'unauthenticated');
});

it('répond 403 forbidden sans renvoyer le message brut de la policy', function (): void {
    $response = $this->getJson('/api/v1/_test/forbidden');

    expectApiError($response, 403, 'forbidden');
    expect($response->json('message'))->toBe(__('auth.api.forbidden'));
});

it('répond 404 not_found pour une route inconnue', function (): void {
    $response = $this->getJson('/api/v1/route-inconnue');

    expectApiError($response, 404, 'not_found');
    expect($response->json('message'))->toBe(__('auth.api.not_found'));
});

it('répond 409 conflict pour un abort(409), sans le message brut', function (): void {
    $response = $this->getJson('/api/v1/_test/conflict');

    expectApiError($response, 409, 'conflict');
    expect($response->json('message'))->toBe(__('errors.api.conflict'));
});

it('répond 422 validation_failed en gardant message et errors de Laravel', function (): void {
    $response = $this->postJson('/api/v1/_test/validate', []);

    expectApiError($response, 422, 'validation_failed', ['message', 'code', 'errors']);
    $response->assertJsonValidationErrors(['pseudo']);
    expect($response->json('errors.pseudo.0'))->toBe($response->json('message'));
});

it('répond 429 too_many_requests avec retry_after et Retry-After', function (): void {
    config(['xomiton.rate_limits.report_per_hour_per_ip' => 1]);

    $this->postJson('/api/v1/_test/limited')->assertOk();
    $response = $this->postJson('/api/v1/_test/limited');

    expectApiError($response, 429, 'too_many_requests', ['message', 'code', 'retry_after']);
    $response->assertHeader('Retry-After');
    expect($response->json('retry_after'))->toBeInt()->toBeGreaterThan(0)
        ->and($response->json('message'))->not->toContain('Too Many');
});

it('résume plusieurs erreurs de validation en français, sans « (and … more errors) »', function (int $fields, string $suffix): void {
    $body = $fields === 2 ? ['zone' => 3] : [];
    $response = $this->postJson('/api/v1/_test/validate-many', $body);

    expectApiError($response, 422, 'validation_failed', ['message', 'code', 'errors']);
    expect($response->json('message'))->toBe($response->json('errors.pseudo.0').' '.$suffix)
        ->not->toContain('more error');
})->with([
    'deux erreurs' => [2, '(et 1 autre erreur)'],
    'trois erreurs' => [3, '(et 2 autres erreurs)'],
]);

it('répond 419 session_expired au vous sur le groupe public et au tu sur l’espace connecté', function (): void {
    $public = $this->getJson('/api/v1/_test/session');

    expectApiError($public, 419, 'session_expired');
    expect($public->json('message'))->toBe(__('errors.419.public.text'));

    Sanctum::actingAs(User::factory()->create());
    $member = $this->getJson('/api/v1/_test/member-session');

    expectApiError($member, 419, 'session_expired');
    expect($member->json('message'))->toBe(__('errors.419.member.text'));
});

it('traite abort(429) et abort(405) comme le limiteur et la mauvaise méthode', function (): void {
    $busy = $this->getJson('/api/v1/_test/busy');

    expectApiError($busy, 429, 'too_many_requests', ['message', 'code', 'retry_after']);
    expect($busy->json('retry_after'))->toBe(60)
        ->and($busy->json('message'))->toBe(__('errors.api.throttled', ['seconds' => 60]));

    $wrong = $this->getJson('/api/v1/_test/wrong-method');

    expectApiError($wrong, 405, 'method_not_allowed');
    expect($wrong->json('message'))->toBe(__('auth.api.method_not_allowed'));
});

it('donne un code et un message français aux autres statuts HTTP', function (string $uri, int $status, string $code, string $key): void {
    $response = $this->getJson($uri);

    expectApiError($response, $status, $code);
    expect($response->json('message'))->toBe(__($key));
})->with([
    'bad_request' => ['/api/v1/_test/bad', 400, 'bad_request', 'errors.api.bad_request'],
    'gone' => ['/api/v1/_test/gone', 410, 'gone', 'errors.api.gone'],
    'autre 4xx' => ['/api/v1/_test/teapot', 418, 'http_error', 'errors.api.http'],
]);

it('rend une exception métier avec son statut, son code, ses erreurs, ses extras et ses en-têtes', function (): void {
    $response = $this->getJson('/api/v1/_test/domain');

    expectApiError($response, 409, 'test_conflict', ['message', 'code', 'errors', 'server_version']);
    $response->assertHeader('X-Test', 'oui')
        ->assertJsonPath('message', __('errors.api.conflict'))
        ->assertJsonPath('errors.version.0', __('errors.api.conflict'))
        ->assertJsonPath('server_version', 3);
});

it('rend une exception métier en 422 par défaut', function (): void {
    $response = $this->getJson('/api/v1/_test/domain-default');

    expectApiError($response, 422, 'test_default');
    expect($response->json('message'))->toBe(__('errors.api.validation'));
});

it('masque le détail d’une erreur interne hors débogage', function (): void {
    config(['app.debug' => false]);

    $response = $this->getJson('/api/v1/_test/crash');

    expectApiError($response, 500, 'server_error');
    expect($response->json('message'))->toBe(__('errors.api.server'));
    $response->assertDontSee('secret stack detail');
});

it('laisse la page d’erreur HTML aux routes web hors JSON', function (): void {
    Route::middleware('web')->get('/_test/web-conflict', static fn () => abort(409));

    $response = $this->get('/_test/web-conflict');

    $response->assertStatus(409);
    expect((string) $response->headers->get('Content-Type'))->toContain('text/html');
});
