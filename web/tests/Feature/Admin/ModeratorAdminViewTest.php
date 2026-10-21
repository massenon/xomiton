<?php

declare(strict_types=1);

use App\Application\Admin\AdminNavigation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;

/*
 * P12-09 (ADR-28, Q2) : vue d'administration du rôle `moderateur` — `admin.access` limité, menu filtré par permission
 * (Tableau de bord, Recherche, Réseau social, Modération, Utilisateurs en lecture), 403 sur tous les autres écrans
 * d'administration, qu'ils soient livrés ou encore provisoires. La liste des écrans est lue dans les routes : un écran
 * ajouté plus tard sans permission fait échouer ce test.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->moderator = User::factory()->create();
    $this->moderator->syncRoles(['moderateur']);
});

/** Écrans d'administration sans paramètre, par nom de route. */
function modViewScreens(): array
{
    $screens = [];

    foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
        if (str_starts_with($name, 'admin.') && in_array('GET', $route->methods(), true) && ! str_contains($route->uri(), '{')) {
            $screens[$name] = '/'.$route->uri();
        }
    }

    ksort($screens);

    return $screens;
}

it('ouvre au modérateur le tableau de bord, la recherche, le réseau social, la modération et les utilisateurs, et rien d’autre', function (): void {
    $allowed = ['admin.dashboard', 'admin.recherche', 'admin.reseau', 'admin.moderation', 'admin.utilisateurs'];
    $screens = modViewScreens();

    expect(array_keys($screens))->toContain(...$allowed)->and(count($screens))->toBeGreaterThan(20);

    $this->actingAs($this->moderator);

    foreach ($screens as $name => $uri) {
        $response = $this->get($uri);

        if (in_array($name, $allowed, true)) {
            expect($response->getStatusCode())->toBe(200, $name.' devrait être ouvert au modérateur');
        } else {
            expect($response->getStatusCode())->toBe(403, $name.' devrait être refusé au modérateur');
        }
    }
});

it('filtre le menu du modérateur par permission : Pilotage, Réseau social et Utilisateurs seulement', function (): void {
    $groups = collect(app(AdminNavigation::class)->groups($this->moderator));
    $items = $groups->flatMap(fn (array $group): array => array_column($group['items'], 'key'))->all();

    expect($items)->toBe(['dashboard', 'recherche', 'reseau', 'moderation', 'utilisateurs'])
        ->and($groups->pluck('key')->all())->toBe(['steering', 'social', 'system']);

    $html = $this->actingAs($this->moderator)->get(route('admin.dashboard'))->assertOk()->getContent();

    foreach (['moderation', 'utilisateurs', 'reseau', 'recherche'] as $key) {
        expect($html)->toContain('data-xm-nav="'.$key.'"');
    }

    foreach (['alertes', 'registres', 'journal', 'parametres', 'inscriptions', 'maintenance', 'passerelles', 'rapports'] as $key) {
        expect($html)->not->toContain('data-xm-nav="'.$key.'"');
    }
});

it('donne à l’administrateur tout le menu de gestion des comptes et de la modération', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $items = collect(app(AdminNavigation::class)->groups($admin))->flatMap(fn (array $group): array => array_column($group['items'], 'key'))->all();

    expect($items)->toContain('utilisateurs', 'inscriptions', 'moderation', 'reseau', 'alertes');
});
