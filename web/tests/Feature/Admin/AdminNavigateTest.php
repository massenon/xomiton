<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\File;

/*
 * Navigation sans rechargement de l'administration (spec Partie 1, wire:navigate) : liens internes en wire:navigate,
 * liens externes / téléchargements / aperçu / nouvel onglet en chargement complet, assets chargés une fois, barre latérale
 * et cloche persistées, textes lang/fr, couleur de la barre de progression = jeton --xm-primary.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function navigateAdmin(): User
{
    $user = User::factory()->create();
    $user->syncRoles(['superadmin']);

    return $user;
}

/** Balises <a …> d'un fragment HTML : [balise ouvrante complète, href]. */
function anchorsOf(string $html): array
{
    preg_match_all('/<a\s[^>]*href="([^"]*)"[^>]*>/su', $html, $m, PREG_SET_ORDER);

    return array_map(fn (array $row): array => [$row[0], html_entity_decode($row[1])], $m);
}

it('met wire:navigate (et le prefetch au survol) sur les liens internes du gabarit, pas sur les liens externes', function (): void {
    $html = $this->actingAs(navigateAdmin())->get(route('admin.utilisateurs'))->assertOk()->getContent();
    $sidebar = substr($html, (int) strpos($html, 'id="xmSidebar"'), (int) strpos($html, '</aside>') - (int) strpos($html, 'id="xmSidebar"'));

    // Barre latérale : toutes les entrées de menu et le logo, en navigation.
    $items = array_filter(anchorsOf($sidebar), fn (array $a): bool => str_contains($a[0], 'data-xm-nav="'));
    expect($items)->not->toBeEmpty();
    foreach ($items as [$tag]) {
        expect($tag)->toContain('wire:navigate')->toContain('wire:navigate.hover');
    }

    // Site public (nouvel onglet) et espace membre : chargement complet. Déconnexion : formulaire POST, pas de lien.
    $tagFor = fn (string $href): string => collect(anchorsOf($html))->first(fn (array $a): bool => $a[1] === $href)[0] ?? '';
    expect($tagFor(route('home')))->not->toContain('wire:navigate')->toContain('target="_blank"')
        ->and($tagFor(route('fil')))->not->toContain('wire:navigate')->not->toBe('')
        ->and($tagFor(route('profil')))->not->toContain('wire:navigate')->not->toBe('')
        ->and($html)->toMatch('#<form method="post" action="[^"]*/deconnexion">#');

    // Fil d'Ariane, recherche mobile, menu du compte : en navigation.
    expect($tagFor(route('admin.dashboard')))->toContain('wire:navigate')
        ->and($tagFor(route('admin.recherche')))->toContain('wire:navigate')
        ->and($tagFor(route('admin.parametres')))->toContain('wire:navigate')
        ->and($html)->toContain('data-xm-navigate-form');
});

it('charge admin.js une seule fois dans le head (data-navigate-track) et persiste barre latérale, cloche et annonceur', function (): void {
    $html = $this->actingAs(navigateAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();
    $head = substr($html, 0, (int) strpos($html, '</head>'));

    expect(preg_match_all('#<script type="module" src="[^"]*admin-[^"]*\.js"[^>]*data-navigate-track="reload"#', $head))->toBe(1)
        ->and(preg_match_all('#<script type="module" src="[^"]*admin-[^"]*\.js"#', $html))->toBe(1)
        ->and($html)->toContain('x-persist="admin-sidebar"')
        ->toContain('x-persist="admin-bell"')
        ->toContain('x-persist="admin-announcer"')
        ->toContain('id="xmRouteAnnouncer" role="status" aria-live="polite"')
        ->toContain('<h1 class="h5 mb-0 fw-bold text-truncate" tabindex="-1">');

    // Un seul élément de chaque : @persist exige des noms uniques.
    foreach (['admin-sidebar', 'admin-bell', 'admin-announcer'] as $name) {
        expect(substr_count($html, 'x-persist="'.$name.'"'))->toBe(1);
    }
});

it('pose l’état du menu pour la barre persistée et les textes de navigation lus depuis lang/fr', function (): void {
    $html = $this->actingAs(navigateAdmin())->get(route('admin.registres'))->assertOk()->getContent();

    preg_match('#<script type="application/json" id="xmNavState">(.*?)</script>#s', $html, $state);
    preg_match('#<script type="application/json" id="xmAdminI18n">(.*?)</script>#s', $html, $i18n);

    expect(json_decode($state[1], true))->toMatchArray(['current' => 'registres'])->toHaveKey('badges')
        ->and(json_decode($i18n[1], true))->toMatchArray([
            'pageAnnounce' => __('admin.nav.page_announce'),
            'navReload' => __('admin.nav.reloading'),
        ]);
});

it('définit les textes de la navigation sans rechargement (annonce « Page : titre », relais de chargement)', function (): void {
    expect(__('admin.nav.page_announce', ['title' => 'Alertes']))->toBe("Page\u{A0}: Alertes")
        ->and(__('admin.nav.page_announce'))->toContain(':title')
        ->and(__('admin.nav.reloading'))->toBe('Rechargement de la page…')
        ->and(File::get(resource_path('js/admin/i18n.js')))->toContain('pageAnnounce')->toContain('navReload');
});

it('règle la barre de progression de Livewire sur le jeton --xm-primary', function (): void {
    $tokens = File::get(resource_path('css/theme/tokens.css'));
    preg_match('/--xm-primary:\s*var\((--[a-z0-9-]+)\)/', $tokens, $alias);
    preg_match('/'.preg_quote($alias[1], '/').':\s*(#[0-9A-Fa-f]{6})/', $tokens, $hex);

    expect(config('livewire.navigate.show_progress_bar'))->toBeTrue()
        ->and(strtolower((string) config('livewire.navigate.progress_bar_color')))->toBe(strtolower($hex[1]));
});

it('aligne le nonce CSP : tous les scripts en ligne du gabarit portent le nonce de la réponse', function (): void {
    $response = $this->actingAs(navigateAdmin())->get(route('admin.dashboard'))->assertOk();
    preg_match("/'nonce-([A-Za-z0-9+\\/=]+)'/", (string) $response->headers->get('Content-Security-Policy'), $nonce);
    $html = $response->getContent();

    // Aucun <script> exécutable sans nonce (hors scripts de données JSON et sources de même origine).
    preg_match_all('#<script(?![^>]*type="application/(?:json|ld\+json)")(?![^>]*\ssrc=)([^>]*)>#', $html, $inline);
    expect($inline[1])->not->toBeEmpty();
    foreach ($inline[1] as $attributes) {
        expect($attributes)->toContain('nonce="'.$nonce[1].'"');
    }
});

/**
 * Régression : tout lien des vues d'administration est en wire:navigate, sauf liste blanche — nouvel onglet, site public,
 * espace membre, téléchargements signés, PDF, exports, aperçu de maintenance, ancres et liens externes.
 */
it('n’oublie wire:navigate sur aucun lien interne des vues d’administration (liste blanche explicite)', function (): void {
    $files = collect(File::allFiles(resource_path('views/livewire/admin')))
        ->merge([
            new SplFileInfo(resource_path('views/partials/admin-sidebar.blade.php')),
            new SplFileInfo(resource_path('views/partials/admin-topbar.blade.php')),
            new SplFileInfo(resource_path('views/components/layouts/admin.blade.php')),
        ]);

    // Itinéraires d'administration qui doivent charger la page en entier (fichier, PDF, aperçu).
    $fullLoadRoutes = ['admin.sauvegardes.telecharger', 'admin.maintenance.preview', 'admin.fiches.pdf', 'admin.export'];
    // Variables qui portent un lien de téléchargement ou de pièce jointe signé.
    $fullLoadVariables = ['$links[', '$attachment['];
    // Itinéraires du site public ou de l'espace membre.
    $outsideRoutes = ['home', 'fil', 'profil', 'inscription', 'groupes.show', 'pages.show', 'evenements.show', 'forums.show', 'fil.show'];

    $missing = [];

    foreach ($files as $file) {
        $source = File::get($file->getPathname());
        // Les commentaires Blade n'ont pas de balises à vérifier.
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;

        preg_match_all('/<a\s[^>]*href="[^"]*"[^>]*>/su', $source, $tags);

        foreach ($tags[0] as $tag) {
            if (str_contains($tag, 'wire:navigate') || str_contains($tag, 'target="_blank"')) {
                continue;
            }
            if (preg_match('/href="(?:https?:\/\/|mailto:|tel:|#)/', $tag) === 1) {
                continue;
            }
            $allowed = false;
            foreach ([...$fullLoadRoutes, ...$outsideRoutes] as $route) {
                if (str_contains($tag, "route('".$route."'")) {
                    $allowed = true;
                }
            }
            foreach ($fullLoadVariables as $variable) {
                if (str_contains($tag, '{{ '.$variable)) {
                    $allowed = true;
                }
            }
            if (! $allowed) {
                $missing[] = basename(dirname($file->getPathname())).'/'.$file->getFilename().' : '.mb_substr($tag, 0, 140);
            }
        }
    }

    expect($missing)->toBe([]);
});

it('garde le chargement complet pour les téléchargements, PDF et l’aperçu de maintenance', function (): void {
    foreach (['backups/index', 'forms/index', 'maintenance/index', 'reports/index'] as $view) {
        $source = File::get(resource_path('views/livewire/admin/'.$view.'.blade.php'));
        preg_match_all('/<a\s[^>]*(?:telecharger|fiches\.pdf|maintenance\.preview|\$links\[)[^>]*>/su', $source, $tags);

        expect($tags[0])->not->toBeEmpty();
        foreach ($tags[0] as $tag) {
            expect($tag)->not->toContain('wire:navigate');
        }
    }
});

it('suit la navigation sans rechargement pour une notification qui mène à l’administration', function (): void {
    $source = File::get(app_path('Livewire/Admin/NotificationBell.php'));

    expect($source)->toContain("navigate: str_starts_with((string) parse_url(\$url, PHP_URL_PATH), '/admin')");
});
