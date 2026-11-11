<?php

declare(strict_types=1);

use App\Application\Admin\Dashboard\DashboardStats;
use App\Enums\AgeRange;
use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\Sex;
use App\Livewire\Admin\Dashboard\Index as Dashboard;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/*
 * P10-04 : tableau de bord /admin — accès, blocs selon la permission, bandeau « Alertes SOS actives » et accusé de
 * réception (audit), 7 KPI dont le taux de synchro (< 24 h / appareils actifs), cellules de 1 à 4 masquées (KPI,
 * graphiques et tableaux de données), Chart.js chargé à la demande, dernières alertes, carte schématique des SOS.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
    Cache::flush();
    $this->admin = User::factory()->create();
    $this->admin->syncRoles(['admin']);
});

function dashUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function dash(?User $user = null)
{
    test()->actingAs($user ?? test()->admin);

    return Livewire::test(Dashboard::class);
}

function casesOf(int $count, array $attributes = []): void
{
    CaseRecord::factory()->count($count)->create($attributes + ['occurred_on' => now()->subDays(3)->toDateString()]);
}

it('sert le tableau de bord à l’admin, au superadmin et au modérateur, pas au membre', function (): void {
    foreach (['admin', 'superadmin', 'moderateur'] as $role) {
        $this->actingAs(dashUser($role))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeLivewire(Dashboard::class)
            ->assertSee('Tableau de bord');
    }

    $this->actingAs(dashUser('membre'))->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs(dashUser('pair'))->get(route('admin.dashboard'))->assertForbidden();
});

it('montre au modérateur ses seuls blocs : comptes et raccourcis, ni alertes, ni dossiers, ni synchro', function (): void {
    Alert::factory()->sos()->create();
    casesOf(6);

    dash(dashUser('moderateur'))
        ->assertViewHas('canAlerts', false)
        ->assertViewHas('canCases', false)
        ->assertViewHas('cases', null)
        ->assertViewHas('platform', null)
        ->assertViewHas('syncRate', null)
        ->assertDontSee(['Alertes SOS actives', 'Dernières alertes', 'Taux de synchro', 'Nouvelles alertes'])
        ->assertSee('Comptes')
        ->assertSee('Raccourcis');
});

it('affiche le bandeau des SOS actifs en tête, avec l’accusé de réception, et se tait sans SOS', function (): void {
    dash()->assertDontSee('Alerte SOS active')->assertDontSeeHtml('data-xm-sos-banner');

    $sos = Alert::factory()->sos()->create();
    Alert::factory()->sos()->withStatus(AlertStatus::Handled)->create();
    Alert::factory()->sos()->create(['cancelled_at' => now()]);

    $component = dash()
        ->assertSeeHtml('data-xm-sos-banner')
        ->assertSee('1 alerte SOS active')
        ->assertSee('Accuser réception');

    expect(substr_count($component->html(), 'xm-sos-banner__icon'))->toBe(1);

    $component->call('acknowledge', $sos->id)->assertDispatched('toast', type: 'success', message: 'Réception confirmée.');

    expect($sos->fresh()->isAcknowledged())->toBeTrue()
        ->and($sos->fresh()->status)->toBe(AlertStatus::Acknowledged)
        ->and(AuditLog::query()->ofAction('alerts.acknowledged')->forSubject($sos)->value('user_id'))->toBe($this->admin->id);

    $component->assertSee('Réception accusée')->assertDontSee('Accuser réception');
});

it('refuse l’accusé de réception hors permission ou hors périmètre', function (): void {
    $sos = Alert::factory()->sos()->create();

    dash(dashUser('moderateur'))->call('acknowledge', $sos->id)->assertDispatched('toast', type: 'error', message: 'Alerte introuvable.');
    dash()->call('acknowledge', 999999)->assertDispatched('toast', type: 'error', message: 'Alerte introuvable.');

    expect($sos->fresh()->isAcknowledged())->toBeFalse();
});

it('annonce un SOS nouveau au sondage (son) sans rejouer les SOS déjà connus', function (): void {
    $known = Alert::factory()->sos()->create();

    $component = dash()->assertSet('knownSos', [$known->id])
        ->call('refresh')
        ->assertNotDispatched('xm-sos-new');

    $fresh = Alert::factory()->sos()->create();
    $component->call('refresh')->assertDispatched('xm-sos-new', count: 1)->assertSet('knownSos', fn (array $ids): bool => in_array($fresh->id, $ids, true));
    $component->call('refresh')->assertNotDispatched('xm-sos-new');
});

it('compte les 7 KPI : alertes, dossiers (masqués sous 5), comptes et taux de synchro', function (): void {
    Alert::factory()->count(2)->create();
    Alert::factory()->sos()->create();
    casesOf(4, ['status' => CaseStatus::Open]);
    casesOf(6, ['status' => CaseStatus::InProgress]);
    casesOf(5, ['status' => CaseStatus::Closed]);

    $html = dash()->html();

    foreach (['Nouvelles alertes', 'SOS actifs', 'Cas ouverts', 'Cas en cours', 'Cas clos', 'Comptes', 'Taux de synchro'] as $label) {
        expect($html)->toContain($label);
    }

    // 4 cas ouverts : « données masquées » ; suppression complémentaire de la plus petite autre cellule (clos, 5) : la somme
    // des trois KPI est un total connu, la seule cellule masquée s'en déduirait. 6 : affiché.
    dash()
        ->assertViewHas('cases', ['open' => null, 'in_progress' => 6, 'closed' => null])
        ->assertViewHas('alertCounters', ['new' => 3, 'sos_active' => 1]);

    expect(substr_count($html, 'données masquées'))->toBeGreaterThanOrEqual(1)
        ->and($html)->toMatch('/Cas ouverts.{0,400}données masquées/s')
        ->and($html)->not->toMatch('/Cas ouverts.{0,200}<div class="kpi-value">4</s');
});

it('calcule le taux de synchro : appareils synchronisés depuis moins de 24 h / appareils actifs', function (): void {
    expect(app(DashboardStats::class)->syncRate())->toBeNull();

    SyncDevice::factory()->count(3)->create(['last_sync_at' => now()->subHours(2)]);
    SyncDevice::factory()->stale()->create();
    SyncDevice::factory()->revoked()->create(['last_sync_at' => now()->subHour()]);

    expect(app(DashboardStats::class)->syncRate())->toBe(['rate' => 75.0, 'synced' => 3, 'active' => 4]);

    dash()->assertSee("75\u{00A0}%")->assertSee('3 sur 4 appareils synchronisés en moins de 24 h');
});

it('affiche « — » sans appareil actif et ne montre pas la synchro hors permission', function (): void {
    dash()->assertSee('Aucun appareil actif');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin.access', 'alerts.view']);
    dash($viewer)->assertDontSee('Taux de synchro');
});

it('masque les cellules de 1 à 4 dans les graphiques et leur tableau de données', function (): void {
    $zoneA = Zone::factory()->create(['name' => 'Zone Alpha']);
    $zoneB = Zone::factory()->create(['name' => 'Zone Bravo']);
    casesOf(5, ['zone_id' => $zoneA->id, 'commune_id' => null, 'sex' => Sex::Femme, 'age_range' => AgeRange::From18To24]);
    casesOf(4, ['zone_id' => $zoneB->id, 'commune_id' => null, 'sex' => Sex::Homme, 'age_range' => AgeRange::From25To34]);
    casesOf(2, ['zone_id' => $zoneB->id, 'commune_id' => null, 'status' => CaseStatus::Archived, 'sex' => Sex::Autre, 'age_range' => AgeRange::From10To14]);

    $charts = collect(app(DashboardStats::class)->charts())->keyBy('key');

    expect($charts->keys()->all())->toBe(['month', 'violence', 'zone', 'population', 'sex_age'])
        ->and($charts['zone']['labels'])->toBe(['Zone Alpha', 'Zone Bravo'])
        ->and($charts['zone']['datasets'][0]['data'])->toBe([null, null])
        ->and($charts['zone']['masked'])->toBeTrue()
        ->and($charts['month']['datasets'][0]['data'])->toHaveCount(12);

    // Octobre (3 jours avant le 2 novembre) : 9 dossiers non archivés (5 + 4) → 9, affiché ; novembre : 0, affiché.
    expect(array_slice($charts['month']['datasets'][0]['data'], -2))->toBe([9, 0]);

    $sexAge = $charts['sex_age'];
    $femme = collect($sexAge['datasets'])->firstWhere('label', 'Femme');
    $homme = collect($sexAge['datasets'])->firstWhere('label', 'Homme');
    // 4 masqué, donc 5 aussi (suppression complémentaire sur l'ensemble des cellules du graphique).
    expect($femme['data'][array_search('18-24', array_column(AgeRange::cases(), 'value'), true)])->toBeNull()
        ->and($homme['data'][array_search('25-34', array_column(AgeRange::cases(), 'value'), true)])->toBeNull();

    $html = dash()->html();
    expect($html)->toContain('Zone Alpha')->toContain('Zone Bravo')
        ->and($html)->toContain('données masquées')
        ->and($html)->toContain('data-xm-needs="chart"')
        ->and($html)->toContain('xmDashboardCharts');
});

it('ne laisse jamais une seule cellule masquée par graphique, ni par les KPI : pas de retrouvaille par différence', function (): void {
    $zoneA = Zone::factory()->create(['name' => 'Zone Alpha']);
    $zoneB = Zone::factory()->create(['name' => 'Zone Bravo']);
    $zoneC = Zone::factory()->create(['name' => 'Zone Charlie']);
    // Mois = 9 + 20 (affiché) ; zone = [20, 5, 4] : sans suppression complémentaire, la cellule masquée vaudrait 29 - 25 = 4.
    casesOf(20, ['zone_id' => $zoneA->id, 'commune_id' => null]);
    casesOf(5, ['zone_id' => $zoneB->id, 'commune_id' => null]);
    casesOf(4, ['zone_id' => $zoneC->id, 'commune_id' => null]);

    $charts = collect(app(DashboardStats::class)->charts())->keyBy('key');

    expect($charts['month']['datasets'][0]['data'])->toContain(29)
        ->and($charts['zone']['datasets'][0]['data'])->toBe([20, null, null]);

    foreach ($charts as $chart) {
        $hidden = collect($chart['datasets'])->sum(fn (array $dataset): int => count(array_filter($dataset['data'], is_null(...))));
        expect($hidden)->not->toBe(1);
    }

    // KPI : 4 / 6 / 20 → ouverts masqué, donc le plus petit autre (en cours, 6) aussi.
    $kpis = app(DashboardStats::class)->caseCounts();
    expect(count(array_filter($kpis, is_null(...))))->not->toBe(1);
});

it('charge Chart.js à la demande sur le tableau de bord et lui seul', function (): void {
    casesOf(6);

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('data-xm-needs="chart"', false)
        ->assertSee('data-xm-screen="dashboard"', false)
        ->assertSee('Cas enregistrés par mois', false);

    $this->actingAs($this->admin)->get(route('admin.alertes'))->assertDontSee('data-xm-needs="chart"', false);
});

it('liste les dernières alertes avec « Prendre en charge » et la carte schématique des SOS', function (): void {
    $zone = Zone::factory()->create(['name' => 'Littoral']);
    Alert::factory()->for($zone, 'zone')->sos()->located(6.37, 2.42)->create();
    $report = Alert::factory()->for($zone, 'zone')->create();

    $component = dash()
        ->assertSee('Dernières alertes')
        ->assertSee('Prendre en charge')
        ->assertSeeHtml('class="pin"')
        ->assertSee('Carte schématique des SOS actifs', false);

    $component->call('acknowledge', $report->id);
    expect($report->fresh()->status)->toBe(AlertStatus::Acknowledged);

    // Position schématique grossie (case de 5 %) et bornée au cadre.
    $pin = Dashboard::pin(Alert::factory()->sos()->located(6.37, 2.42)->create());
    expect($pin['left'])->toBeBetween(5, 95)->and($pin['top'])->toBeBetween(5, 95)
        ->and(fmod($pin['left'], Dashboard::PIN_STEP))->toBe(0.0)->and(fmod($pin['top'], Dashboard::PIN_STEP))->toBe(0.0);
    expect(Dashboard::pin(Alert::factory()->sos()->create(['lat' => null, 'lng' => null])))->toBeNull();
});

it('ne rend aucune coordonnée exacte ni lien Maps sur le tableau de bord (seul le détail les lit, avec audit)', function (): void {
    $sos = Alert::factory()->sos()->located(6.371234, 2.418765)->create();

    $component = dash();
    $html = $component->html();

    expect($html)->not->toContain('6.371234')->not->toContain('2.418765')
        ->not->toContain('6.37')->not->toContain('2.42')
        ->not->toContain('google.com/maps')
        ->and($html)->toContain(route('admin.alertes', ['q' => '#'.$sos->id]));

    // Le sondage ne transporte pas non plus la position exacte.
    $component->call('refresh');
    expect($component->html())->not->toContain('6.371234')->not->toContain('google.com/maps')
        ->and(AuditLog::query()->ofAction('alerts.viewed')->exists())->toBeFalse();
});

it('annonce le vrai nombre de SOS actifs, même au-delà des 20 de la liste', function (): void {
    Alert::factory()->sos()->count(25)->create();

    $html = dash()->assertViewHas('alertCounters', fn (array $c): bool => $c['sos_active'] === 25)->html();

    expect($html)->toContain('25 SOS actifs')->toContain('Voir les 25 SOS');
});

it('ne met en région live (role=alert) que le titre du bandeau SOS, sans durée relative', function (): void {
    Alert::factory()->sos()->create(['created_at' => now()->subMinutes(7)]);

    $html = dash()->html();

    expect(substr_count($html, 'role="alert"'))->toBe(1);
    preg_match('/<div role="alert"[^>]*>(.*?)<\/div>/s', $html, $live);
    expect($live[1] ?? '')->toContain('alerte SOS active')->not->toContain('il y a');
    // Les durées relatives restent dans le bandeau, hors de la région live.
    expect($html)->toContain('il y a 7');
});

it('ne montre pas le bloc alertes à un compte alerts.view_routed seul (l’écran des alertes lui est fermé)', function (): void {
    $agent = User::factory()->create();
    $agent->givePermissionTo(['admin.access', 'alerts.view_routed']);
    Alert::factory()->sos()->create();

    dash($agent)->assertViewHas('canAlerts', false)->assertDontSee('Dernières alertes')->assertDontSeeHtml('data-xm-sos-banner');
});

it('affiche l’état de la plateforme aux comptes qui voient les paramètres, et les raccourcis permis', function (): void {
    DirectoryContact::factory()->create();

    dash()
        ->assertSee('État de la plateforme')
        ->assertSee(['Version', 'Inscriptions', 'Mode maintenance', 'Synchronisation hors ligne', 'Comptes en attente de validation', 'E-mails en attente'])
        ->assertSee(['Traiter les alertes', 'Annuaire', 'Inscriptions'])
        ->assertViewHas('shortcuts', fn (array $s): bool => count($s) >= 5);
});

it('actualise le tableau de bord toutes les 30 s', function (): void {
    dash()->assertSeeHtml('wire:poll.30s="refresh"');
});
