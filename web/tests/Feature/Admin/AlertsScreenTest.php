<?php

declare(strict_types=1);

use App\Application\Admin\Alerts\AlertDesk;
use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\DirectoryRole;
use App\Livewire\Admin\Alerts\Index as AlertsScreen;
use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\AlertNote;
use App\Models\AlertStatusChange;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\DirectoryContact;
use App\Models\OnCallShift;
use App\Models\User;
use App\Models\Zone;
use App\Support\CsvExport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * P10-05 : Alertes & signalements /admin/alertes (Livewire, WithDataTable) — accès par permission et par périmètre
 * (AlertScope), onglets Toutes · SOS · Signalements · Besoins et compteurs, filtres et recherche sur colonnes en clair
 * (un mot du récit ne filtre pas, ADR-33), prendre en charge, changer de statut, réattribuer, note interne chiffrée
 * jamais exposée sans `alerts.manage`, détail journalisé, export CSV sans GPS ni récit, cadence d'actualisation, Chart.js
 * non chargé.
 */

const RECIT = 'Le récit secret du voisin qui rôde la nuit';

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->syncRoles(['admin']);
});

function alertsUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function screen(?User $user = null)
{
    test()->actingAs($user ?? test()->admin);

    return Livewire::test(AlertsScreen::class);
}

it('sert l’écran à l’admin et au superadmin, le refuse au modérateur, au pair et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(alertsUser($role))->get(route('admin.alertes'))
            ->assertOk()
            ->assertSeeLivewire(AlertsScreen::class)
            ->assertSee('Alertes & signalements')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="alertes"\s+aria-current="page"/');
    }

    $this->actingAs(alertsUser('moderateur'))->get(route('admin.alertes'))->assertForbidden();
    $this->actingAs(alertsUser('pair'))->get(route('admin.alertes'))->assertForbidden();
    $this->actingAs(alertsUser('membre'))->get(route('admin.alertes'))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.alertes'))->assertRedirect();
});

it('affiche les onglets, leurs compteurs et filtre la liste par type (libellés Q29)', function (): void {
    Alert::factory()->sos()->create();
    Alert::factory()->ofType(AlertType::Report)->count(2)->create();
    Alert::factory()->ofType(AlertType::Need)->create();

    screen()
        ->assertSee(['Toutes', 'SOS', 'Signalements', 'Besoins'])
        ->assertDontSee('Dénonciations')
        ->assertViewHas('tabCounts', ['all' => 4, 'sos' => 1, 'reports' => 2, 'needs' => 1])
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 4)
        ->call('setTab', 'sos')
        ->assertSet('tab', 'sos')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1 && $rows->first()->type === AlertType::Sos)
        ->assertViewHas('tabCounts', ['all' => 4, 'sos' => 1, 'reports' => 2, 'needs' => 1])
        ->call('setTab', 'reports')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2)
        ->call('setTab', 'inconnu')
        ->assertSet('tab', 'all');
});

it('ignore un onglet ou des filtres mal formés dans l’URL (200, jamais 500)', function (): void {
    Alert::factory()->count(2)->create();

    $this->actingAs($this->admin);
    Livewire::withQueryParams(['onglet' => 'zzz', 'st' => 'nimporte', 'pr' => ['x'], 'zone' => 'abc', 'cat' => '../etc', 'par' => 'abc', 'tri' => ['a']])
        ->test(AlertsScreen::class)
        ->assertSet('tab', 'all')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2);
});

it('filtre par statut, priorité, département et catégorie, et compte les filtres actifs', function (): void {
    $littoral = Zone::factory()->create(['name' => 'Littoral']);
    $borgou = Zone::factory()->create(['name' => 'Borgou']);
    Alert::factory()->for($littoral, 'zone')->create(['category' => 'physique', 'priority' => AlertPriority::Critical]);
    Alert::factory()->for($borgou, 'zone')->create(['category' => 'sexuelle', 'status' => AlertStatus::InProgress]);
    Alert::factory()->for($borgou, 'zone')->create(['category' => 'physique', 'status' => AlertStatus::Handled]);

    screen()
        ->set('status', 'en_cours')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->call('resetFilters')
        ->set('priority', 'critique')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1 && $rows->first()->zone_id === $littoral->id)
        ->call('resetFilters')
        ->set('zone', (string) $borgou->id)
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2)
        ->set('category', 'physique')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->assertSet('status', '')
        ->call('resetFilters')
        ->assertSet('zone', '')
        ->assertSet('category', '')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 3);
});

it('recherche sur le n°, le code de suivi, le pseudonyme, la commune et la personne routée, jamais sur le récit', function (): void {
    $zone = Zone::factory()->create(['name' => 'Atlantique']);
    $commune = Commune::factory()->for($zone)->create(['name' => 'Abomey-Calavi']);
    $contact = DirectoryContact::factory()->create(['name' => 'ZINSOU Mireille', 'role' => DirectoryRole::Juriste]);
    $target = Alert::factory()->for($zone, 'zone')->create(['commune_id' => $commune->id, 'routed_to' => $contact->id, 'is_anonymous' => false, 'pseudo' => 'Lune_douce', 'description' => RECIT]);
    // Zone et commune fixées : la fabrique peut tirer « Atlantique » ou « Abomey-Calavi » et fausser le décompte (test instable).
    $other = Alert::factory()->for(Zone::factory()->create(['name' => 'Mono']), 'zone')->create(['commune_id' => null, 'description' => 'Autre histoire']);

    $found = fn ($rows): bool => $rows->total() === 1 && $rows->first()->is($target);

    screen()
        ->set('search', '#'.$target->id)->assertViewHas('rows', $found)
        ->set('search', (string) $target->id)->assertViewHas('rows', $found)
        ->set('search', $target->tracking_code)->assertViewHas('rows', $found)
        ->set('search', $target->displayCode())->assertViewHas('rows', $found)
        ->set('search', 'lune_douce')->assertViewHas('rows', $found)
        ->set('search', 'Calavi')->assertViewHas('rows', $found)
        ->set('search', 'Atlantique')->assertViewHas('rows', $found)
        ->set('search', 'Mireille')->assertViewHas('rows', $found)
        // ADR-33 : un mot du récit chiffré ne filtre pas la liste (il ne la vide pas non plus par erreur de colonne).
        ->set('search', 'rôde')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0)
        ->set('search', 'secret')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0)
        // Jokers LIKE neutralisés.
        ->set('search', '%')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0);

    expect($other->exists)->toBeTrue();
});

it('n’affiche ni le récit, ni la position, ni le contact dans la liste', function (): void {
    Alert::factory()->sos()->located(6.3654, 2.4183)->withContact('+229 01 97 00 00 00')->create(['description' => RECIT]);

    $html = screen()->html();

    expect($html)->not->toContain('rôde')
        ->and($html)->not->toContain('6.3654')
        ->and($html)->not->toContain('+229 01 97 00 00 00')
        ->and($html)->toContain('Position transmise');
});

it('prend en charge une alerte nouvelle : accusé, statut, historique et audit', function (): void {
    $alert = Alert::factory()->sos()->create();

    screen()
        ->call('take', $alert->id)
        ->assertDispatched('toast', type: 'success', message: 'Alerte prise en charge.');

    $fresh = $alert->fresh();
    expect($fresh->status)->toBe(AlertStatus::Acknowledged)
        ->and($fresh->acknowledged_at)->not->toBeNull()
        ->and($fresh->acknowledged_by)->toBe($this->admin->id)
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->where('to_status', 'pris_en_charge')->exists())->toBeTrue()
        ->and(AuditLog::query()->ofAction('alerts.acknowledged')->forSubject($alert)->value('user_id'))->toBe($this->admin->id);

    // Déjà prise : message d’information, aucune seconde écriture.
    screen()->call('take', $alert->id)->assertDispatched('toast', type: 'info');
    expect(AuditLog::query()->ofAction('alerts.acknowledged')->count())->toBe(1);
});

it('refuse de prendre en charge une alerte hors périmètre ou inexistante', function (): void {
    screen()->call('take', 999999)->assertDispatched('toast', type: 'error', message: 'Alerte introuvable.');
});

it('ouvre le détail : récit, contact et position déchiffrés, lien Maps, ouverture journalisée', function (): void {
    $alert = Alert::factory()->sos()->located(6.3654, 2.4183)->withContact('+229 01 97 00 00 00')->create(['description' => RECIT, 'location_accuracy' => 12]);

    $component = screen()->call('openDetail', $alert->id)
        ->assertSet('detailId', $alert->id)
        ->assertDispatched('xm-modal', id: 'xmAlertDetail', action: 'show')
        ->assertSee(RECIT)
        ->assertSee('+229 01 97 00 00 00')
        ->assertSeeHtml('https://www.google.com/maps?q=6.3654,2.4183')
        ->assertSeeHtml('rel="noopener noreferrer"');

    $log = AuditLog::query()->ofAction('alerts.viewed')->forSubject($alert)->first();
    expect($log)->not->toBeNull()->and($log->user_id)->toBe($this->admin->id)->and((string) $log->detail)->not->toContain('rôde');

    // Le snapshot Livewire ne contient aucune donnée déchiffrée.
    $snapshot = json_encode($component->snapshot, JSON_UNESCAPED_UNICODE);
    expect($snapshot)->not->toContain('rôde')->and($snapshot)->not->toContain('6.3654')->and($snapshot)->not->toContain('+229 01 97');

    $component->call('closeDetail')->assertSet('detailId', null)->assertDontSee(RECIT);
});

it('ne permet pas de changer l’alerte ouverte depuis le navigateur (propriété verrouillée)', function (): void {
    $alert = Alert::factory()->create();

    expect(fn () => screen()->set('detailId', $alert->id))->toThrow(Exception::class);
});

it('change le statut selon les transitions fermées, avec historique et audit', function (): void {
    $alert = Alert::factory()->create();

    screen()->call('openDetail', $alert->id)
        ->set('newStatus', 'en_cours')
        ->call('changeStatus')
        ->assertDispatched('toast', type: 'success')
        ->assertHasNoErrors();

    $fresh = $alert->fresh();
    expect($fresh->status)->toBe(AlertStatus::InProgress)
        ->and($fresh->acknowledged_at)->not->toBeNull()
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->where('to_status', 'en_cours')->value('user_id'))->toBe($this->admin->id)
        ->and((string) AuditLog::query()->ofAction('alerts.status_changed')->forSubject($alert)->value('detail'))->toContain('vers=en_cours');

    // Hors liste blanche : refusé sans écriture.
    screen()->call('openDetail', $alert->id)->set('newStatus', 'invalide')->call('changeStatus')->assertHasErrors('newStatus');
    // Transition fermée (en cours → nouveau) : message du domaine sur le champ.
    screen()->call('openDetail', $alert->id)->set('newStatus', 'nouveau')->call('changeStatus')->assertHasErrors('newStatus');
    expect($alert->fresh()->status)->toBe(AlertStatus::InProgress);
});

it('ne propose pas de transition à une alerte close et la détaille sans formulaire', function (): void {
    $alert = Alert::factory()->withStatus(AlertStatus::Closed)->create();

    screen()->call('openDetail', $alert->id)
        ->assertDontSeeHtml('id="xmAlertStatus"')
        ->assertDontSeeHtml('id="xmAlertRouteTo"');
});

it('réattribue à un contact actif, trace l’escalade manuelle et journalise', function (): void {
    $zone = Zone::factory()->create();
    $alert = Alert::factory()->for($zone, 'zone')->create();
    $contact = DirectoryContact::factory()->inZone($zone)->role(DirectoryRole::Psychologue)->create(['name' => 'AGBO Carine']);
    $inactive = DirectoryContact::factory()->inZone($zone)->inactive()->create();

    screen()->call('openDetail', $alert->id)
        ->assertSee('Aperçu du routage automatique')
        ->set('reassignTo', (string) $contact->id)
        ->call('reassign')
        ->assertDispatched('toast', type: 'success', message: 'Alerte routée vers AGBO Carine.');

    $fresh = $alert->fresh();
    expect($fresh->routed_to)->toBe($contact->id)
        ->and($fresh->routing_fallback->value)->toBe('manual')
        ->and(AlertEscalation::query()->where('alert_id', $alert->id)->where('reason', AlertEscalation::REASON_MANUAL)->where('to_contact_id', $contact->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->ofAction('alerts.reassigned')->forSubject($alert)->exists())->toBeTrue();

    screen()->call('openDetail', $alert->id)->set('reassignTo', (string) $inactive->id)->call('reassign')->assertHasErrors('reassignTo');
    screen()->call('openDetail', $alert->id)->set('reassignTo', 'abc')->call('reassign')->assertHasErrors('reassignTo');
    expect($alert->fresh()->routed_to)->toBe($contact->id);
});

it('propose les contacts du même département, filtrés par rôle', function (): void {
    $zone = Zone::factory()->create();
    $alert = Alert::factory()->for($zone, 'zone')->create();
    $here = DirectoryContact::factory()->inZone($zone)->role(DirectoryRole::Juriste)->create(['name' => 'ICI Juriste']);
    $there = DirectoryContact::factory()->role(DirectoryRole::Juriste)->create(['name' => 'AILLEURS Juriste']);
    $psy = DirectoryContact::factory()->inZone($zone)->role(DirectoryRole::Psychologue)->create(['name' => 'ICI Psy']);

    $names = fn ($component): array => $component->detail['contacts']->pluck('name')->sort()->values()->all();

    $component = screen()->call('openDetail', $alert->id);
    expect($names($component))->toBe(['ICI Juriste', 'ICI Psy']);

    $component->set('reassignRole', 'juriste');
    expect($names($component))->toBe(['ICI Juriste']);

    $component->set('reassignSameZone', false);
    expect($names($component))->toBe(['AILLEURS Juriste', 'ICI Juriste']);

    expect([$here->exists, $there->exists, $psy->exists])->toBe([true, true, true]);
});

it('ajoute une note interne chiffrée, sans le corps dans l’audit, et la montre dans l’historique', function (): void {
    $alert = Alert::factory()->create();

    screen()->call('openDetail', $alert->id)
        ->set('noteBody', '  Appel passé, la personne est en lieu sûr.  ')
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSet('noteBody', '')
        ->assertSee('Appel passé, la personne est en lieu sûr.')
        ->assertSee('Note interne');

    $raw = DB::table('alert_notes')->where('alert_id', $alert->id)->value('body');
    expect($raw)->not->toContain('Appel passé')
        ->and(AlertNote::query()->where('alert_id', $alert->id)->value('body'))->toBe('Appel passé, la personne est en lieu sûr.');

    $audit = AuditLog::query()->ofAction('alerts.note_added')->forSubject($alert)->first();
    expect($audit)->not->toBeNull()->and((string) $audit->detail)->not->toContain('Appel passé');

    screen()->call('openDetail', $alert->id)->set('noteBody', '   ')->call('addNote')->assertHasErrors('noteBody');
    screen()->call('openDetail', $alert->id)->set('noteBody', str_repeat('a', 1001))->call('addNote')->assertHasErrors('noteBody');
    expect(AlertNote::query()->count())->toBe(1);
});

it('n’expose les notes internes qu’à qui détient alerts.manage', function (): void {
    $alert = Alert::factory()->create();
    AlertNote::factory()->create(['alert_id' => $alert->id, 'user_id' => $this->admin->id, 'body' => 'Note confidentielle de l’équipe']);

    // `alerts.view` + `admin.access` sans `alerts.manage` : voit l’alerte, jamais les notes ni le formulaire de note.
    $reader = User::factory()->create();
    $reader->givePermissionTo(['admin.access', 'alerts.view']);

    $component = screen($reader)->call('openDetail', $alert->id)
        ->assertDontSee('Note confidentielle de l’équipe')
        ->assertDontSeeHtml('id="xmAlertNote"');

    expect($component->detail['notes_visible'])->toBeFalse()->and($component->detail['can_note'])->toBeFalse();

    // Appel direct de l’action : refusé par la policy.
    $component->set('noteBody', 'Tentative')->call('addNote')->assertDispatched('toast', type: 'error');
    expect(AlertNote::query()->count())->toBe(1);

    screen()->call('openDetail', $alert->id)->assertSee('Note confidentielle de l’équipe')->assertSeeHtml('id="xmAlertNote"');
});

it('cloisonne un compte limité aux alertes routées vers sa fiche d’annuaire', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin.access', 'alerts.view_routed', 'alerts.take']);
    $contact = DirectoryContact::factory()->linkedTo($viewer)->create();
    $mine = Alert::factory()->routedTo($contact)->create();
    $theirs = Alert::factory()->create();

    screen($viewer)
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1 && $rows->first()->is($mine))
        ->call('openDetail', $theirs->id)
        ->assertSet('detailId', null)
        ->assertDispatched('toast', type: 'error', message: 'Alerte introuvable.')
        ->call('take', $theirs->id)
        ->assertDispatched('toast', type: 'error');

    expect($theirs->fresh()->status)->toBe(AlertStatus::New);

    // Sans `alerts.manage` : ni réattribution ni export.
    screen($viewer)->call('openDetail', $mine->id)->assertDontSeeHtml('id="xmAlertRouteTo"')->assertDontSee('Exporter CSV');
    screen($viewer)->call('export')->assertForbidden();
});

it('ferme /admin/alertes à un compte alerts.view_routed seul (403), la route étant sous can:alerts.view', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin.access', 'alerts.view_routed', 'alerts.take']);

    $this->actingAs($viewer)->get(route('admin.alertes'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.alertes'))->assertOk();
});

it('calcule « Prendre en charge » en une fois pour la page : le nombre de requêtes de routage ne dépend pas du nombre de lignes', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin.access', 'alerts.view_routed', 'alerts.take']);
    $contact = DirectoryContact::factory()->linkedTo($viewer)->create();

    $routingQueries = function () use ($viewer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        screen($viewer)->assertSeeHtml('wire:click="take(');
        $count = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'directory_contacts') && str_contains($q['query'], 'user_id'))->count();
        DB::disableQueryLog();

        return $count;
    };

    Alert::factory()->routedTo($contact)->create();
    $one = $routingQueries();
    Alert::factory()->count(5)->routedTo($contact)->create();

    // L'ancien `@can('take')` par ligne ajoutait une requête `isRoutedTo` par alerte.
    expect($one)->toBeGreaterThan(0)->and($routingQueries())->toBe($one);
});

it('exporte le CSV des filtres actifs sans récit, GPS, contact ni note, et journalise l’export', function (): void {
    $zone = Zone::factory()->create(['name' => 'Littoral']);
    $alert = Alert::factory()->sos()->for($zone, 'zone')->located(6.3654, 2.4183)->withContact('+229 01 97 00 00 00')->create(['description' => RECIT, 'category' => 'physique']);
    AlertNote::factory()->create(['alert_id' => $alert->id, 'user_id' => $this->admin->id, 'body' => 'Note secrète']);
    Alert::factory()->ofType(AlertType::Report)->create(['description' => 'Autre récit confidentiel']);

    $component = screen()->call('setTab', 'sos')->call('export');
    $url = data_get($component->effects, 'redirect');
    expect($url)->toBeString()->toContain('/admin/export');

    $content = $this->get($url)->assertOk()->streamedContent();

    expect(str_starts_with($content, CsvExport::BOM))->toBeTrue()
        ->and($content)->toContain($alert->displayCode())->toContain('Littoral')->toContain('SOS')
        ->and($content)->not->toContain('rôde')->not->toContain('secret')->not->toContain('6.3654')->not->toContain('2.4183')
        ->and($content)->not->toContain('+229 01 97')->not->toContain('Autre récit')->not->toContain('Note secrète')
        ->and(AuditLog::query()->ofAction('export.csv')->where('detail', 'like', '%alertes%')->exists())->toBeTrue();

    $header = strtok(ltrim($content, CsvExport::BOM), "\n");
    expect($header)->not->toContain('Description')->not->toContain('Latitude')->not->toContain('Contact');
});

it('actualise la liste toutes les 10 s pour les agent·es de garde et 30 s pour les autres, sauf modale ouverte', function (): void {
    $alert = Alert::factory()->create();
    $onCall = alertsUser('admin');
    OnCallShift::factory()->admin()->forUser($onCall)->create();
    $notOnCall = alertsUser('admin');

    expect(app(AlertDesk::class)->pollSeconds($onCall))->toBe(10)
        ->and(app(AlertDesk::class)->pollSeconds($notOnCall))->toBe(30);

    screen($onCall)->assertSeeHtml('wire:poll.10s')->call('openDetail', $alert->id)->assertDontSeeHtml('wire:poll.10s');
    screen($notOnCall)->assertSeeHtml('wire:poll.30s')->assertDontSeeHtml('wire:poll.10s');
});

it('ne charge pas Chart.js sur l’écran des alertes', function (): void {
    Alert::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.alertes'))
        ->assertOk()
        ->assertDontSee('data-xm-needs="chart"', false)
        ->assertDontSee('xmDashboardCharts', false);
});

it('affiche les KPI et un état vide sans alerte', function (): void {
    screen()
        ->assertSee(['Nouvelles alertes', 'SOS actifs', 'Priorité critique ouvertes', 'Traitées'])
        ->assertSee('Aucune alerte ne correspond aux filtres.');

    Alert::factory()->sos()->create();
    Alert::factory()->priority(AlertPriority::Critical)->withStatus(AlertStatus::Handled)->create();

    screen()->assertViewHas('kpis', ['new' => 1, 'sos_active' => 1, 'critical_open' => 1, 'handled' => 1]);
});
