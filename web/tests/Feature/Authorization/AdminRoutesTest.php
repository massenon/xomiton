<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tests\Feature\Authorization\Support\RoleUsers;

/*
 * Routes /admin/* (bootstrap/app.php : auth + can:admin.access) : 403 pour membre et pair, 200 pour l'équipe
 * d'administration (écrans provisoires), redirection vers /connexion pour un·e visiteur·se.
 */

beforeEach(fn () => RoleUsers::seedRoles());

/**
 * @return list<string>
 */
function adminUris(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'admin.') && in_array('GET', $route->methods(), true))
        ->map(fn ($route): string => '/'.ltrim($route->uri(), '/'))
        ->values()
        ->all();
}

it('expose un ensemble non vide d’écrans d’administration', function (): void {
    expect(adminUris())->not->toBeEmpty()->toContain('/admin', '/admin/utilisateurs', '/admin/journal');
});

it('refuse chaque écran d’administration aux rôles sans admin.access', function (string $role): void {
    $this->actingAs(RoleUsers::make($role));

    foreach (adminUris() as $uri) {
        $this->get($uri)->assertForbidden();
    }
})->with(['membre', 'pair']);

it('sert chaque écran d’administration aux rôles avec admin.access, sauf ceux d’une permission propre qu’ils n’ont pas', function (string $role): void {
    $user = RoleUsers::make($role);
    $this->actingAs($user);

    foreach (adminUris() as $uri) {
        // Permission propre à l'écran (ex. `can:platform.maintenance`, P13-06) en plus de `admin.access`.
        $route = Route::getRoutes()->match(Illuminate\Http\Request::create($uri));
        $ability = collect($route->gatherMiddleware())
            ->first(fn (string $m): bool => str_starts_with($m, 'can:') && $m !== 'can:admin.access');
        $allowed = $ability === null || $user->can(substr((string) $ability, 4));

        $allowed ? $this->get($uri)->assertOk() : $this->get($uri)->assertForbidden();
    }
})->with(['moderateur', 'admin', 'superadmin']);

it('renvoie un·e visiteur·se vers la connexion et un compte sans rôle vers un 403', function (): void {
    $this->get('/admin')->assertRedirect('/connexion');
    $this->get('/admin/journal')->assertRedirect('/connexion');

    $this->actingAs(App\Models\User::factory()->create());
    $this->get('/admin')->assertForbidden();
});
