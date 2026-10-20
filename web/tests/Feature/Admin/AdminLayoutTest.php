<?php

declare(strict_types=1);

use App\Application\Admin\AdminNavigation;
use App\Application\Admin\CsvExportLink;
use App\Enums\AlertStatus;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;

/*
 * P10-01 : gabarit d'administration — barre latérale Q14 a (8 groupes de dashboard-admin.md + Raccourcis), entrées
 * filtrées par permission (vue du modérateur, P12-09), entrée courante `aria-current="page"` (déduite de la route sur
 * les écrans provisoires), offcanvas < 992 px, bouton « Replier le menu », pastilles alertes / comptes en attente,
 * barre supérieure (recherche Ctrl K documentée, plein écran, cloche Livewire), message flashé rejoué en toast.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    AdminNavigation::forgetCounters();
});

function layoutUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

/** Libellés des groupes visibles, dans l'ordre. */
function navGroups(string $html): array
{
    preg_match_all('/<div class="group" id="xmNavGroup-[a-z]+">([^<]+)<\/div>/u', $html, $m);

    return array_map('html_entity_decode', $m[1]);
}

it('range les 24 écrans et le mode maintenance dans les 8 groupes de Q14, sans écran oublié ni inventé', function (): void {
    $mapped = collect(AdminNavigation::GROUPS)->flatten(1)->pluck(0)->all();
    $screens = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route): string => (string) $route->getName())
        ->filter(fn (string $name): bool => str_starts_with($name, 'admin.') && substr_count($name, '.') === 1)
        ->reject(fn (string $name): bool => $name === CsvExportLink::ROUTE) // route technique (export CSV signé), pas un écran
        ->map(fn (string $name): string => substr($name, 6))
        ->values()->all();

    expect(array_keys(AdminNavigation::GROUPS))->toBe(['steering', 'cases', 'directory', 'content', 'data', 'reports', 'social', 'system'])
        ->and($mapped)->toHaveCount(25)
        ->and(array_diff($screens, $mapped))->toBe([])
        ->and(array_diff($mapped, $screens))->toBe([]);
});

it('montre à l’admin les 8 groupes, l’offcanvas, le bouton de repli et la cloche Livewire', function (): void {
    $html = $this->actingAs(layoutUser('superadmin'))->get(route('admin.utilisateurs'))->assertOk()->getContent();

    expect(navGroups($html))->toBe(['Pilotage', 'Cas & alertes', 'Annuaire', 'Contenus', 'Données', 'Rapports', 'Réseau social', 'Système', 'Raccourcis'])
        ->and($html)->toContain('class="xm-admin-sidebar offcanvas-lg offcanvas-start" id="xmSidebar"')
        ->toContain('data-bs-toggle="offcanvas" data-bs-target="#xmSidebar"')
        ->toContain('id="xmSidebarCollapse"')
        ->toContain('Replier le menu')
        ->toContain('aria-label="Navigation de l’administration"')
        ->toContain('data-xm-admin-bell')
        ->toContain('wire:poll.30s')
        ->toContain('aria-keyshortcuts="Control+K Meta+K"')
        ->toContain('Raccourci clavier')
        ->toContain('id="xmAdminI18n"')
        ->toMatch('/data-xm-nav="utilisateurs"\s+aria-current="page"/');

    expect(substr_count($html, 'aria-current="page"'))->toBe(2); // entrée du menu + fil d'Ariane
});

it('filtre le menu par permission : le modérateur ne voit que Pilotage, Réseau social et Utilisateurs', function (): void {
    $html = $this->actingAs(layoutUser('moderateur'))->get(route('admin.dashboard'))->assertOk()->getContent();

    expect(navGroups($html))->toBe(['Pilotage', 'Réseau social', 'Système', 'Raccourcis'])
        ->and($html)->toContain('data-xm-nav="moderation"')
        ->toContain('data-xm-nav="reseau"')
        ->toContain('data-xm-nav="utilisateurs"')
        ->not->toContain('data-xm-nav="alertes"')
        ->not->toContain('data-xm-nav="journal"')
        ->not->toContain('data-xm-nav="maintenance"')
        ->not->toContain('data-xm-nav="passerelles"');
});

it('affiche les pastilles alertes nouvelles et comptes en attente avec un texte pour les lecteurs d’écran', function (): void {
    Alert::factory()->count(2)->create(['status' => AlertStatus::New]);
    Alert::factory()->create(['status' => AlertStatus::Closed]);
    User::factory()->create(['status' => UserStatus::Pending]);

    $html = $this->actingAs(layoutUser('admin'))->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('(2 nouvelles alertes)')
        ->toContain('(1 compte en attente de validation)')
        ->toMatch('/<span class="badge bg-danger"\s+aria-hidden="true"\s*>2<\/span>/');

    // Le modérateur ne voit pas l'entrée Alertes, donc pas son compteur.
    $moderator = $this->actingAs(layoutUser('moderateur'))->get(route('admin.dashboard'))->getContent();
    expect($moderator)->not->toContain('nouvelles alertes');
});

it('rejoue un message flashé en toast (template lu par admin.js) et n’affiche plus d’alerte Bootstrap', function (): void {
    $html = $this->actingAs(layoutUser('admin'))->withSession(['toast' => 'Contact mis à jour.', 'toast_type' => 'success'])
        ->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('<template data-xm-flash-toast data-type="success">Contact mis à jour.</template>')
        ->not->toContain('alert-dismissible fade show');

    $this->flushSession();
    $status = $this->actingAs(layoutUser('admin'))->withSession(['status' => 'Statut <b>gras</b>', 'status_type' => 'warning" onclick="x'])
        ->get(route('admin.dashboard'))->getContent();
    expect($status)->toContain('<template data-xm-flash-toast data-type="success">Statut &lt;b&gt;gras&lt;/b&gt;</template>');
});

it('mémorise le repli de la barre latérale avant le premier rendu (script avec nonce)', function (): void {
    $html = $this->actingAs(layoutUser('admin'))->get(route('admin.dashboard'))->getContent();

    expect($html)->toMatch('/<script nonce="[^"]+">try \{ if \(localStorage\.getItem\(\'xm-admin-sidebar\'\) === \'collapsed\'\)/');
});
