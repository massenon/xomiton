<?php

declare(strict_types=1);

use App\Enums\AlertStatus;
use App\Livewire\Admin\Journal\Index as Journal;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Tests\Support\Livewire\AlertsDataTableProbe;

/*
 * P10-02 : trait WithDataTable (Livewire::test sur l'écran témoin Journal d'audit et sur une sonde à colonnes chiffrées) —
 * tri par liste blanche, pagination serveur 10 / 25 / 50 (25 par défaut, Q23), recherche limitée aux colonnes en clair
 * (ADR-33 : un mot du récit ne filtre pas la liste), filtres et recherche lus et écrits dans l'URL (?q=&st=…),
 * remise en page 1 à chaque changement, « Réinitialiser ».
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->syncRoles(['admin']);
    $this->actingAs($this->admin);
});

/** Entrées du journal avec des dates distinctes (la plus récente : index 0). */
function journalEntries(int $count, array $attributes = []): void
{
    for ($i = 0; $i < $count; $i++) {
        AuditLog::factory()->create($attributes + ['created_at' => now()->subMinutes($i + 1), 'action' => 'auth.authenticated', 'actor' => 'agent'.$i.'@xomiton.test', 'detail' => 'Connexion n°'.$i]);
    }
}

describe('pagination serveur (Q23)', function (): void {
    it('pagine par 25 par défaut et propose 10, 25 et 50', function (): void {
        journalEntries(60);

        Livewire::test(Journal::class)
            ->assertSet('perPage', 25)
            ->assertViewHas('rows', fn ($rows): bool => $rows->count() === 25 && $rows->total() === 60 && $rows->perPage() === 25)
            ->assertSee('Affichage de 1 à 25 sur 60 entrées')
            ->assertSeeHtml('<option value="10">10</option>')
            ->assertSeeHtml('<option value="50">50</option>')
            ->set('perPage', 10)
            ->assertViewHas('rows', fn ($rows): bool => $rows->count() === 10 && $rows->lastPage() === 6)
            ->call('gotoPage', 3)
            ->assertSee('Affichage de 21 à 30 sur 60 entrées')
            ->assertSeeHtml('aria-label="Page 3, page actuelle"');
    });

    it('refuse une taille de page hors liste et revient en page 1 quand la taille change', function (): void {
        journalEntries(30);

        Livewire::withQueryParams(['par' => '999'])->test(Journal::class)
            ->assertViewHas('rows', fn ($rows): bool => $rows->perPage() === 25)
            ->set('perPage', 10)
            ->call('gotoPage', 2)
            ->assertViewHas('rows', fn ($rows): bool => $rows->currentPage() === 2)
            ->set('perPage', 50)
            ->assertViewHas('rows', fn ($rows): bool => $rows->currentPage() === 1 && $rows->count() === 30)
            ->set('perPage', 7)
            ->assertSet('perPage', 25);
    });
});

describe('tri', function (): void {
    it('trie par date décroissante par défaut puis par colonne autorisée, aria-sort sur la seule colonne triée', function (): void {
        AuditLog::factory()->create(['action' => 'maintenance.enabled', 'actor' => 'b@x.test', 'created_at' => now()->subDays(2)]);
        AuditLog::factory()->create(['action' => 'alerts.created', 'actor' => 'c@x.test', 'created_at' => now()->subDay()]);
        AuditLog::factory()->create(['action' => 'auth.logout', 'actor' => 'a@x.test', 'created_at' => now()->subHours(1)]);

        $component = Livewire::test(Journal::class)
            ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('action')->all() === ['auth.logout', 'alerts.created', 'maintenance.enabled'])
            ->assertSeeHtml('aria-sort="descending"');

        $component->call('sortBy', 'action')
            ->assertSet('sort', 'action')->assertSet('direction', 'asc')
            ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('action')->all() === ['alerts.created', 'auth.logout', 'maintenance.enabled'])
            ->assertSeeHtml('aria-sort="ascending"')
            ->call('sortBy', 'action')
            ->assertSet('direction', 'desc')
            ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('action')->first() === 'maintenance.enabled')
            ->call('sortBy', 'compte')
            ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('actor')->all() === ['a@x.test', 'b@x.test', 'c@x.test']);

        expect(substr_count($component->html(), 'aria-sort='))->toBe(1);
    });

    it('ignore une colonne de tri hors liste blanche (URL ou appel forgé)', function (): void {
        journalEntries(3);

        Livewire::withQueryParams(['tri' => 'ip', 'sens' => 'n’importe'])->test(Journal::class)
            ->assertViewHas('rows', fn ($rows): bool => $rows->first()?->actor === 'agent0@xomiton.test')
            ->call('sortBy', 'detail; drop table users')
            ->assertSet('sort', 'ip')
            ->assertViewHas('rows', fn ($rows): bool => $rows->count() === 3);
    });
});

describe('recherche et filtres dans l’URL', function (): void {
    it('cherche dans les colonnes en clair et lit / écrit ?q= et les filtres de l’écran', function (): void {
        journalEntries(3);
        AuditLog::factory()->create(['action' => 'maintenance.enabled', 'actor' => 'superadmin@xomiton.test', 'detail' => 'Motif : migration de la base']);

        Livewire::withQueryParams(['q' => 'migration'])->test(Journal::class)
            ->assertSet('search', 'migration')
            ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
            ->set('search', 'agent1@')
            ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1 && $rows->first()->actor === 'agent1@xomiton.test');

        Livewire::withQueryParams(['ac' => 'maintenance.enabled'])->test(Journal::class)
            ->assertSet('action', 'maintenance.enabled')
            ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
            ->assertSee('1 filtre actif');
    });

    it('neutralise les jokers LIKE de la saisie', function (): void {
        journalEntries(2);

        Livewire::test(Journal::class)->set('search', '%')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0)
            ->set('search', '_')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0);
    });

    it('revient en page 1 quand la recherche ou un filtre change, et « Réinitialiser » vide tout', function (): void {
        journalEntries(30);

        Livewire::test(Journal::class)
            ->call('gotoPage', 2)
            ->set('search', 'agent')
            ->assertViewHas('rows', fn ($rows): bool => $rows->currentPage() === 1)
            ->call('gotoPage', 2)
            ->set('action', 'auth.authenticated')
            ->assertViewHas('rows', fn ($rows): bool => $rows->currentPage() === 1)
            ->assertSee('2 filtres actifs')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('action', '')
            ->assertSee('Affichage de 1 à 25 sur 30 entrées');
    });
});

describe('ADR-33 : colonnes chiffrées', function (): void {
    it('un mot du récit chiffré ne filtre jamais la liste ; la catégorie (en clair) la filtre', function (): void {
        Alert::factory()->create(['category' => 'physique', 'description' => 'Mon voisin me menace avec un couteau', 'status' => AlertStatus::New]);
        Alert::factory()->create(['category' => 'numerique', 'description' => 'Harcèlement sur les réseaux', 'status' => AlertStatus::Closed]);

        Livewire::test(AlertsDataTableProbe::class)
            ->set('search', 'couteau')
            ->assertSeeHtml('<span data-total>0</span>')
            ->set('search', 'numer')
            ->assertSeeHtml('<span data-total>1</span>')
            ->assertSee('numerique');

        Livewire::withQueryParams(['st' => AlertStatus::New->value])->test(AlertsDataTableProbe::class)
            ->assertSet('status', AlertStatus::New->value)
            ->assertSeeHtml('<span data-total>1</span>')
            ->assertSee('physique');
    });

    it('refuse une colonne chiffrée déclarée cherchable ou triable, qualifiée ou jointe', function (string $property, string $column, string $reported): void {
        Alert::factory()->create();

        // L'exception naît au rendu de la liste : la vue l'enveloppe (ViewException), la cause reste la LogicException.
        try {
            Livewire::test(AlertsDataTableProbe::class)->set($property, $column)->set('search', 'x')->call('sortBy', 'danger');
            $this->fail('Colonne chiffrée acceptée');
        } catch (Throwable $e) {
            $cause = $e instanceof LogicException ? $e : $e->getPrevious();
            expect($cause)->toBeInstanceOf(LogicException::class)->and($cause->getMessage())->toContain('ADR-33', $reported);
        }
    })->with([
        'recherche' => ['unsafeSearch', 'description', 'alerts.description'],
        'tri' => ['unsafeSort', 'description', 'alerts.description'],
        'colonne qualifiée' => ['unsafeSearch', 'alerts.contact', 'alerts.contact'],
        'jointure' => ['unsafeSort', 'profiles.orientation', 'profiles.orientation'],
    ]);
});

describe('sélection', function (): void {
    it('coche et décoche toute la page, et la sélection se vide quand un filtre change', function (): void {
        journalEntries(3);

        Livewire::test(Journal::class)
            ->call('togglePageSelection', [1, 2, 3])
            ->assertSet('selected', ['1', '2', '3'])
            ->call('togglePageSelection', [1, 2, 3])
            ->assertSet('selected', [])
            ->call('togglePageSelection', [2])
            ->set('search', 'agent')
            ->assertSet('selected', []);
    });
});

describe('export CSV complet (ADR-33)', function (): void {
    it('exige cases.export pour un export complet (403), même avec le droit de voir l’écran', function (): void {
        Alert::factory()->create(['category' => 'physique', 'description' => 'Récit confidentiel']);
        $agent = User::factory()->create();
        $agent->syncRoles(['moderateur']);
        $agent->givePermissionTo('alerts.view');
        $this->actingAs($agent);

        Livewire::test(AlertsDataTableProbe::class)->call('export', true)->assertForbidden();
        Livewire::test(AlertsDataTableProbe::class)->call('export')->assertRedirect();
    });

    it('journalise distinctement l’export complet et seul lui contient le récit', function (): void {
        Alert::factory()->create(['category' => 'physique', 'description' => 'Récit confidentiel', 'status' => AlertStatus::New]);
        Alert::factory()->create(['category' => 'numerique', 'description' => 'Autre récit', 'status' => AlertStatus::Closed]);

        $filtered = Livewire::withQueryParams(['st' => AlertStatus::New->value])->test(AlertsDataTableProbe::class)->call('export')->effects['redirect'];
        // withQueryParams reste mémorisé par le gestionnaire Livewire (singleton) : on le vide pour le second composant.
        $full = Livewire::withQueryParams([])->test(AlertsDataTableProbe::class)->call('export', true)->effects['redirect'];

        $plain = $this->get($filtered)->assertOk()->streamedContent();
        $complete = $this->get($full)->assertOk()->streamedContent();

        expect($plain)->toContain('physique')->not->toContain('numerique')->not->toContain('Récit')
            ->and($complete)->toContain('Récit confidentiel', 'Autre récit')
            ->and(AuditLog::query()->where('action', 'export.csv')->sole()->detail)->toBe('Export CSV sonde-alertes, 1 ligne')
            ->and(AuditLog::query()->where('action', 'export.csv_full')->sole()->detail)->toBe('Export CSV complet sonde-alertes, 2 lignes');
    });

    it('rejoue les contrôles au téléchargement : un compte qui a perdu cases.export reçoit 403', function (): void {
        Alert::factory()->create();
        $url = Livewire::test(AlertsDataTableProbe::class)->call('export', true)->effects['redirect'];
        $this->admin->syncRoles(['moderateur']);
        $this->admin->givePermissionTo('alerts.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get($url)->assertForbidden();
        expect(AuditLog::query()->where('action', 'like', 'export.%')->count())->toBe(0);
    });
});
