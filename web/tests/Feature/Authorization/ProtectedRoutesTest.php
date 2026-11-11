<?php

declare(strict_types=1);

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Authorization\Support\RoleUsers;

/*
 * P16-04 : « tests 403 pour toutes les routes protégées ». Le parcours se fait sur la table de routes réelle
 * (équivalent de `php artisan route:list --json`) : toute route portant un middleware d'authentification
 * (`auth`, `auth:sanctum`) ou d'autorisation (`can:*`, EnsurePeerSpaceAccess) ne répond jamais 200 à un·e visiteur·se ;
 * toute route d'administration (`can:admin.access`) ou de l'espace pair répond 403/404 à un membre simple.
 */

beforeEach(fn () => RoleUsers::seedRoles());

/**
 * @return list<LaravelRoute>
 */
function protectedRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(function (LaravelRoute $route): bool {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }
                if (str_starts_with($middleware, 'auth') || str_starts_with($middleware, 'can:')
                    || str_contains($middleware, 'Authenticate') || str_contains($middleware, 'Authorize')
                    || str_contains($middleware, 'EnsurePeerSpaceAccess')) {
                    return true;
                }
            }

            return false;
        })
        ->values()
        ->all();
}

/**
 * Valeur d'un paramètre : première alternative d'une contrainte `whereIn` / énumération, sinon la valeur candidate.
 */
function whereSample(LaravelRoute $route, string $name, string $fallback): string
{
    $pattern = $route->wheres[$name] ?? null;

    if (is_string($pattern) && preg_match('/^[\w\-|]+$/', $pattern) === 1 && str_contains($pattern, '|')) {
        return explode('|', $pattern)[0];
    }

    return $fallback;
}

/**
 * URI concrète : chaque paramètre prend la première valeur plausible qui satisfait les contraintes `where` de la route
 * (l'autorisation précède la liaison de modèle ; un 404 de non-correspondance ne prouverait rien).
 */
function concreteUri(LaravelRoute $route, string $method): string
{
    $candidates = ['1', 'abc', 'abc-def', '01ARZ3NDEKTSV4RRFFQ69G5FAV', '00000000-0000-4000-8000-000000000000', str_repeat('a', 40), 'fr'];

    foreach ($candidates as $value) {
        $uri = '/'.ltrim((string) preg_replace_callback(
            '/\{(\w+)(?::\w+)?\??\}/',
            fn (array $m): string => str_contains($m[1], 'token') ? str_repeat('a', 40) : whereSample($route, $m[1], $value),
            $route->uri(),
        ), '/');

        if ($route->matches(Illuminate\Http\Request::create($uri, $method), false)) {
            return $uri;
        }
    }

    return '/'.ltrim($route->uri(), '/');
}

/**
 * @return list<string> méthode + URI + statut des routes en défaut
 */
function offendingRoutes(array $routes, callable $isAllowedStatus): array
{
    $bad = [];

    foreach ($routes as $route) {
        $method = collect($route->methods())->first(fn (string $m): bool => ! in_array($m, ['HEAD', 'OPTIONS'], true));
        $response = test()->json($method, concreteUri($route, $method), [], ['Accept' => 'text/html,application/json']);
        $status = $response->getStatusCode();

        if (! $isAllowedStatus($status)) {
            $bad[] = $method.' '.$route->uri().' -> '.$status;
        }
    }

    return $bad;
}

it('expose un ensemble significatif de routes protégées', function (): void {
    expect(count(protectedRoutes()))->toBeGreaterThan(150);
});

it('ne répond jamais 200 à un·e visiteur·se sur une route protégée', function (): void {
    $bad = offendingRoutes(protectedRoutes(), fn (int $s): bool => in_array($s, [301, 302, 303, 401, 403], true));

    expect($bad)->toBe([]);
});

dataset('rolesSansAccesPrivilegie', ['membre simple' => 'membre']);

it('refuse à un membre simple chaque route d’administration ou d’espace pair', function (string $role): void {
    $this->actingAs(RoleUsers::make($role));

    $routes = array_values(array_filter(protectedRoutes(), function (LaravelRoute $route): bool {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === 'can:admin.access' || str_contains($middleware, 'EnsurePeerSpaceAccess'))) {
                return true;
            }
        }

        return false;
    }));

    expect(count($routes))->toBeGreaterThan(30);

    $bad = offendingRoutes($routes, fn (int $s): bool => in_array($s, [403, 404], true));

    expect($bad)->toBe([]);
})->with('rolesSansAccesPrivilegie');
