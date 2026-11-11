<?php

declare(strict_types=1);

use App\Application\Steering\IndicatorBoard;
use App\Application\Steering\IndicatorCalculator;
use App\Application\Steering\IndicatorRow;
use App\Application\Steering\Period;
use App\Enums\AlertStatus;
use App\Livewire\Admin\Indicators\Index as Indicators;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\AuditLog;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\IndicatorsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
 * P10-06 : Indicateurs /admin/indicateurs — permissions, formules sur jeux de données connus (IND-04 : premier suivi ≤
 * création + 72 h ; IND-10 : traitée ou close ≤ 24 h ; IND-12 : SOS avec position), masquage des effectifs de 1 à 4 (nombre et
 * dénominateur d'un pourcentage), filtres période / département / fréquence, tendance des instantanés, indicateurs
 * personnalisés (création, valeur, suppression : 422 pour un indicateur intégré), export CSV, tableau « Contenu ».
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(IndicatorsSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(10, 0));
});

function steeringUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function septembre(): Period
{
    return Period::month(2026, 9);
}

function rowOf(array $rows, string $code): IndicatorRow
{
    foreach ($rows as $row) {
        if ($row->indicator->code === $code) {
            return $row;
        }
    }

    throw new RuntimeException("Indicateur {$code} absent.");
}

it('sert l’écran à l’admin et au superadmin, le refuse au modérateur et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(steeringUser($role))->get(route('admin.indicateurs'))
            ->assertOk()
            ->assertSeeLivewire(Indicators::class)
            ->assertSee('Cas enregistrés (VBG + VDH)')
            ->assertSee('Atteinte des cibles')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="indicateurs"\s+aria-current="page"/');
    }

    $this->actingAs(steeringUser('moderateur'))->get(route('admin.indicateurs'))->assertForbidden();
    $this->actingAs(steeringUser('membre'))->get(route('admin.indicateurs'))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.indicateurs'))->assertRedirect();
});

it('calcule IND-04 : premier suivi au plus tard 72 h après la création du dossier', function (): void {
    $pair = User::factory()->create();
    $zone = Zone::factory()->create();
    // Créé le 05/09 à 10 h : limite le 08/09 (inclus).
    $onTime = CaseRecord::factory()->vbg()->create(['zone_id' => $zone->id, 'collected_by' => $pair->id, 'created_at' => '2026-09-05 10:00:00']);
    $late = CaseRecord::factory()->vbg()->create(['zone_id' => $zone->id, 'collected_by' => $pair->id, 'created_at' => '2026-09-05 10:00:00']);
    $none = CaseRecord::factory()->vdh()->create(['zone_id' => $zone->id, 'collected_by' => $pair->id, 'created_at' => '2026-09-06 10:00:00']);
    $firstLate = CaseRecord::factory()->vbg()->create(['zone_id' => $zone->id, 'collected_by' => $pair->id, 'created_at' => '2026-09-10 08:00:00']);

    CaseFollowup::factory()->done()->forCase($onTime)->create(['date' => '2026-09-08']);
    CaseFollowup::factory()->done()->forCase($onTime)->create(['date' => '2026-09-20']);
    CaseFollowup::factory()->done()->forCase($late)->create(['date' => '2026-09-09']);
    // Seul le premier suivi (le plus ancien) compte : un suivi tardif ne défait pas un premier suivi à temps ; ici le premier est tardif (limite le 13/09).
    CaseFollowup::factory()->done()->forCase($firstLate)->create(['date' => '2026-09-16']);
    CaseFollowup::factory()->done()->forCase($firstLate)->create(['date' => '2026-09-14']);

    $result = app(IndicatorCalculator::class)->compute('pct_72h', septembre());

    expect($result->value)->toBe(25.0)
        ->and($result->base)->toBe(4)
        ->and(app(IndicatorCalculator::class)->compute('pct_72h', septembre(), Zone::factory()->create())->isAvailable())->toBeFalse();
});

it('calcule IND-10 : alerte traitée ou close au plus tard 24 h après sa réception', function (): void {
    $fast = Alert::factory()->withStatus(AlertStatus::Handled)->create(['created_at' => '2026-09-02 10:00:00']);
    (new AlertStatusChange)->forceFill(['alert_id' => $fast->id, 'from_status' => AlertStatus::Acknowledged, 'to_status' => AlertStatus::Handled, 'created_at' => '2026-09-03 10:00:00'])->save();
    $slow = Alert::factory()->withStatus(AlertStatus::Closed)->create(['created_at' => '2026-09-02 10:00:00']);
    (new AlertStatusChange)->forceFill(['alert_id' => $slow->id, 'from_status' => AlertStatus::Handled, 'to_status' => AlertStatus::Closed, 'created_at' => '2026-09-03 10:00:01'])->save();
    Alert::factory()->create(['created_at' => '2026-09-04 10:00:00']);
    Alert::factory()->sos()->cancelled()->create(['created_at' => '2026-09-05 10:00:00']);

    $result = app(IndicatorCalculator::class)->compute('pct_alertes24', septembre());

    expect($result->value)->toBe(33.33)->and($result->base)->toBe(3);
});

it('calcule IND-12 : SOS avec une position, sans jamais lire la position', function (): void {
    Alert::factory()->sos()->count(2)->create(['created_at' => '2026-09-05 10:00:00']);
    Alert::factory()->sos()->create(['created_at' => '2026-09-06 10:00:00', 'lat' => null, 'lng' => null]);
    Alert::factory()->create(['created_at' => '2026-09-07 10:00:00']);

    expect(app(IndicatorCalculator::class)->compute('sos', septembre())->value)->toBe(2.0);

    // Aucune formule ne compare, ne filtre ni ne trie une colonne chiffrée : seule la présence de `lat` est testée.
    $source = (string) file_get_contents(app_path('Application/Steering/IndicatorCalculator.php'));
    foreach (config('xomiton.encrypted_columns') as $columns) {
        foreach ($columns as $column) {
            expect($source)->not->toMatch('/(where|orderBy|having)\w*\(\s*[\'"](\w+\.)?'.$column.'[\'"]\s*,\s*[^)]/');
        }
    }
});

it('masque un effectif de 1 à 4 et affiche 5 (nombre) ; masque un pourcentage sur moins de 5 dossiers', function (): void {
    $pair = User::factory()->create();
    CaseRecord::factory()->vbg()->count(4)->create(['collected_by' => $pair->id, 'created_at' => '2026-09-05 10:00:00']);
    $rows = app(IndicatorBoard::class)->rows(septembre());

    expect(rowOf($rows, 'IND-01')->masked)->toBeTrue()
        ->and(rowOf($rows, 'IND-01')->value)->toBeNull()
        ->and(rowOf($rows, 'IND-06')->masked)->toBeTrue()
        ->and(rowOf($rows, 'IND-03')->masked)->toBeFalse()
        ->and(rowOf($rows, 'IND-03')->value)->toBe(0.0);

    CaseRecord::factory()->vbg()->create(['collected_by' => $pair->id, 'created_at' => '2026-09-06 10:00:00']);
    $rows = app(IndicatorBoard::class)->rows(septembre());

    expect(rowOf($rows, 'IND-01')->masked)->toBeFalse()
        ->and(rowOf($rows, 'IND-01')->value)->toBe(5.0)
        ->and(rowOf($rows, 'IND-06')->masked)->toBeFalse();

    $html = $this->actingAs(steeringUser('admin'))->get(route('admin.indicateurs', ['p' => 'perso', 'du' => '2026-09-01', 'au' => '2026-09-30']))->assertOk()->getContent();
    expect($html)->toContain('données masquées');
});

it('calcule l’avancement vers la cible dans le sens souhaité', function (): void {
    $up = Indicator::factory()->make(['target' => 80, 'better' => Indicator::BETTER_UP]);
    $down = Indicator::factory()->make(['target' => 10, 'better' => Indicator::BETTER_DOWN]);
    $zero = Indicator::factory()->make(['target' => 0, 'better' => Indicator::BETTER_DOWN]);

    expect(IndicatorBoard::progress($up, 40))->toBe(50.0)
        ->and(IndicatorBoard::progress($up, 120))->toBe(100.0)
        ->and(IndicatorBoard::progress($down, 8))->toBe(100.0)
        ->and(IndicatorBoard::progress($down, 15))->toBe(50.0)
        ->and(IndicatorBoard::progress($zero, 0))->toBe(100.0)
        ->and(IndicatorBoard::progress($zero, 2))->toBe(50.0)
        ->and(IndicatorBoard::statusOf(100.0))->toBe('reached')
        ->and(IndicatorBoard::statusOf(70.0))->toBe('progress')
        ->and(IndicatorBoard::statusOf(69.9))->toBe('low');
});

it('filtre par période, département et fréquence, et ignore des dates invalides', function (): void {
    $pair = User::factory()->create();
    $zoneA = Zone::factory()->create(['name' => 'Atlantique']);
    $zoneB = Zone::factory()->create(['name' => 'Borgou']);
    CaseRecord::factory()->vbg()->count(6)->create(['zone_id' => $zoneA->id, 'collected_by' => $pair->id, 'created_at' => '2026-09-05 10:00:00']);
    CaseRecord::factory()->vbg()->count(5)->create(['zone_id' => $zoneB->id, 'collected_by' => $pair->id, 'created_at' => '2026-08-05 10:00:00']);
    $this->actingAs(steeringUser('admin'));

    $component = Livewire::test(Indicators::class)->set('preset', 'perso')->set('from', '2026-09-01')->set('until', '2026-09-30');
    $rows = $component->instance()->rows;
    expect(rowOf($rows, 'IND-01')->value)->toBe(6.0);

    $rows = $component->set('zone', (string) $zoneA->id)->instance()->rows;
    expect(rowOf($rows, 'IND-01')->value)->toBe(6.0);
    $rows = $component->set('zone', (string) $zoneB->id)->instance()->rows;
    expect(rowOf($rows, 'IND-01')->value)->toBe(0.0)
        // Indicateur national seul (IND-08) : pas de valeur par département.
        ->and(rowOf($rows, 'IND-08')->value)->toBeNull();

    $weekly = $component->set('zone', '')->set('frequency', 'hebdomadaire')->instance()->rows;
    expect(collect($weekly)->map(fn ($row) => $row->indicator->code)->all())->toBe(['IND-10', 'IND-11', 'IND-12']);

    $component->set('frequency', '')->set('from', '2026-10-01')->set('until', '2026-09-01')
        ->assertSee('Période invalide');
    expect($component->instance()->period->label())->toBe('09/2026');

    $component->call('resetFilters')->assertSet('preset', 'prec')->assertSet('zone', '')->assertSet('frequency', '');

    // Paramètres d'URL mal formés : ignorés, aucune erreur 500.
    $this->get(route('admin.indicateurs', ['p' => ['x'], 'zone' => 'abc', 'freq' => 'inconnue', 'du' => 'pas-une-date']))->assertOk();
});

it('trace la tendance depuis les instantanés mensuels et masque un effectif de 1 à 4 de la courbe', function (): void {
    $indicator = Indicator::query()->whereCode('IND-01')->firstOrFail();
    foreach ([[4, 4], [5, 12], [6, 20], [7, 3], [8, 31], [9, 40]] as [$month, $value]) {
        IndicatorValue::record($indicator, Period::month(2026, $month)->start, Period::month(2026, $month)->end, null, $value);
    }
    $row = rowOf(app(IndicatorBoard::class)->rows(septembre()), 'IND-01');

    expect(array_column($row->trend, 'label'))->toBe(['04/2026', '05/2026', '06/2026', '07/2026', '08/2026', '09/2026'])
        ->and(array_column($row->trend, 'value'))->toBe([null, 12.0, 20.0, null, 31.0, 40.0])
        ->and($row->trendDirection)->toBe('up');

    // Sans instantané : pas de tendance.
    expect(rowOf(app(IndicatorBoard::class)->rows(septembre()), 'IND-02')->trend)->toBe([]);
});

it('crée un indicateur personnalisé à valeur manuelle, avec un code automatique, et le journalise', function (): void {
    $admin = steeringUser('admin');
    $this->actingAs($admin);

    Livewire::test(Indicators::class)
        ->set('label', 'Visites de terrain')->set('definition', 'Visites réalisées par les pairs.')->set('newFrequency', 'trimestrielle')
        ->set('unit', 'nb')->set('target', '40')->set('better', 'up')->set('value', '12')
        ->call('create')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $indicator = Indicator::query()->where('label', 'Visites de terrain')->firstOrFail();
    expect($indicator->code)->toBe('IND-C01')
        ->and($indicator->is_builtin)->toBeFalse()
        ->and($indicator->calc_key)->toBeNull()
        ->and($indicator->frequency->value)->toBe('trimestrielle')
        ->and((float) $indicator->target)->toBe(40.0)
        ->and(IndicatorValue::query()->manual()->where('indicator_id', $indicator->id)->value('value'))->toBe('12.00')
        ->and(AuditLog::query()->where('action', 'indicator.create')->where('user_id', $admin->id)->exists())->toBeTrue();

    Livewire::test(Indicators::class)
        ->set('label', 'Autre')->set('target', '10')->set('value', '1')->call('create')->assertHasNoErrors();
    expect(Indicator::query()->where('label', 'Autre')->value('code'))->toBe('IND-C02');

    $rows = app(IndicatorBoard::class)->rows(septembre());
    expect(rowOf($rows, 'IND-C01')->value)->toBe(12.0)
        ->and(rowOf($rows, 'IND-C01')->progress)->toBe(30.0)
        ->and(rowOf($rows, 'IND-C01')->status)->toBe('low');
});

it('refuse un indicateur personnalisé invalide : libellé, cible, valeur, pourcentage > 100, code déjà pris', function (): void {
    $this->actingAs(steeringUser('admin'));

    Livewire::test(Indicators::class)
        ->call('create')
        ->assertHasErrors(['label' => 'required', 'target' => 'required', 'value' => 'required']);

    Livewire::test(Indicators::class)
        ->set('label', 'Taux')->set('unit', '%')->set('target', '80')->set('value', '130')->call('create')
        ->assertHasErrors(['value' => 'max']);

    Livewire::test(Indicators::class)
        ->set('label', 'Doublon')->set('code', 'ind-01')->set('target', '1')->set('value', '1')->call('create')
        ->assertHasErrors(['code' => 'unique']);

    Livewire::test(Indicators::class)
        ->set('label', 'Code')->set('code', 'trop long code')->set('target', '1')->set('value', '1')->call('create')
        ->assertHasErrors(['code' => 'regex']);

    expect(Indicator::query()->where('is_builtin', false)->count())->toBe(0);
});

it('met à jour la valeur d’un indicateur manuel du mois en cours', function (): void {
    $this->actingAs(steeringUser('admin'));
    $indicator = Indicator::factory()->manual()->create(['code' => 'IND-C09', 'unit' => '%', 'target' => 50]);

    Livewire::test(Indicators::class)
        ->call('editValue', $indicator->id)->assertSet('editId', $indicator->id)
        ->set('editValue', '140')->call('saveValue')->assertHasErrors(['editValue' => 'max'])
        ->set('editValue', '42')->call('saveValue')->assertHasNoErrors();
    Livewire::test(Indicators::class)->set('editValue', '55')->set('editId', $indicator->id)->call('saveValue');

    expect(IndicatorValue::query()->manual()->where('indicator_id', $indicator->id)->count())->toBe(1)
        ->and(IndicatorValue::query()->manual()->where('indicator_id', $indicator->id)->value('value'))->toBe('55.00');
});

it('supprime un indicateur personnalisé mais répond 422 pour un indicateur intégré', function (): void {
    $admin = steeringUser('admin');
    $this->actingAs($admin);
    $custom = Indicator::factory()->manual()->create(['code' => 'IND-C05']);
    IndicatorValue::factory()->manual()->forMonth(2026, 9)->create(['indicator_id' => $custom->id]);
    $builtin = Indicator::query()->whereCode('IND-01')->firstOrFail();

    Livewire::test(Indicators::class)->call('delete', $builtin->id)->assertStatus(422);
    expect(Indicator::query()->whereCode('IND-01')->exists())->toBeTrue()->and(Indicator::query()->count())->toBe(17);

    Livewire::test(Indicators::class)->call('delete', $custom->id)->assertHasNoErrors()->assertDispatched('toast');
    expect(Indicator::query()->whereCode('IND-C05')->exists())->toBeFalse()
        ->and(IndicatorValue::query()->where('indicator_id', $custom->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'indicator.delete')->where('user_id', $admin->id)->exists())->toBeTrue();

    // Aucun bouton de suppression pour les indicateurs intégrés.
    $html = Livewire::test(Indicators::class)->html();
    expect($html)->not->toContain('delete('.$builtin->id.')');
});

it('refuse la création et la suppression à qui n’a pas indicators.manage', function (): void {
    $custom = Indicator::factory()->manual()->create(['code' => 'IND-C07']);

    // Les rôles d'administration portent indicators.manage : on teste avec un compte qui ne peut que lire.
    $reader = User::factory()->create();
    $reader->givePermissionTo(['admin.access', 'indicators.view']);
    $this->actingAs($reader);

    Livewire::test(Indicators::class)->assertOk()->assertDontSee('Indicateur personnalisé')
        ->set('label', 'X')->set('target', '1')->set('value', '1')->call('create')->assertForbidden();
    Livewire::test(Indicators::class)->call('delete', $custom->id)->assertForbidden();
    expect(Indicator::query()->whereCode('IND-C07')->exists())->toBeTrue();
});

it('exporte en CSV les indicateurs filtrés, valeurs masquées comprises, et le journalise', function (): void {
    $admin = steeringUser('admin');
    $this->actingAs($admin);
    CaseRecord::factory()->vbg()->count(3)->create(['collected_by' => User::factory()->create()->id, 'created_at' => '2026-09-05 10:00:00']);

    $component = Livewire::test(Indicators::class)->set('frequency', 'mensuelle')->call('export');
    $component->assertFileDownloaded('xomiton-indicateurs-20261014.csv');
    $csv = (string) base64_decode((string) data_get($component->effects, 'download.content'));

    expect($csv)->toStartWith("\u{FEFF}Code;")
        ->and($csv)->toContain('IND-01;')
        ->and($csv)->toContain('données masquées')
        ->and($csv)->not->toContain('IND-10;')
        ->and($csv)->not->toContain('IND-C');

    $audit = AuditLog::query()->where('action', 'export.csv')->latest('id')->firstOrFail();
    expect($audit->detail)->toContain('indicateurs');
});

it('affiche le tableau « Contenu » : compteurs anonymes, valeurs calculées et masquage', function (): void {
    $this->actingAs(steeringUser('admin'));
    $events = app(App\Application\Steering\ContentEvents::class);
    foreach (range(1, 7) as $i) {
        $events->record(App\Enums\ContentEventKind::SignupClick);
    }
    foreach (range(1, 3) as $i) {
        $events->record(App\Enums\ContentEventKind::EmergencyCall);
    }
    // Délai SOS : cinq SOS accusés après 2, 4, 6, 8 et 10 minutes (médiane 6).
    foreach ([2, 4, 6, 8, 10] as $minutes) {
        Alert::factory()->sos()->create(['created_at' => now()->subDay(), 'acknowledged_at' => now()->subDay()->addMinutes($minutes)]);
    }

    $metrics = collect(app(App\Application\Steering\ContentMetrics::class)->rows(Period::month(2026, 10)))->keyBy('key');

    expect($metrics['signup_click']['value'])->toBe(7)
        ->and($metrics['emergency_call']['masked'])->toBeTrue()
        ->and($metrics['emergency_call']['value'])->toBeNull()
        ->and($metrics['sos_delay']['value'])->toBe(6)
        ->and($metrics['download']['value'])->toBe(0);

    Livewire::test(Indicators::class)->set('preset', 'mois')
        ->assertSee(__('steering.content.metrics.signup_click'))->assertSee(__('steering.content.metrics.sos_delay'))->assertSee('données masquées');
});

it('purge les compteurs d’événements au-delà de 30 jours et compte sans rien identifier', function (): void {
    $events = app(App\Application\Steering\ContentEvents::class);
    $events->record(App\Enums\ContentEventKind::Download);
    $events->record(App\Enums\ContentEventKind::Download);
    App\Models\ContentEvent::query()->create(['kind' => 'download', 'day' => now()->subDays(31)->toDateString(), 'hits' => 9]);

    expect(App\Models\ContentEvent::query()->where('kind', 'download')->whereDate('day', now()->toDateString())->value('hits'))->toBe(2);

    $this->artisan('content-events:purge')->expectsOutputToContain('1 compteur supprimé')->assertSuccessful();
    expect(App\Models\ContentEvent::query()->count())->toBe(1)
        ->and(Illuminate\Support\Facades\Schema::getColumnListing('content_events'))->toBe(['id', 'kind', 'day', 'hits', 'updated_at']);
});

it('compte un événement de contenu par POST anonyme, sans cookie, liste fermée, robots ignorés', function (): void {
    $response = $this->post('/evenements-contenu/signup_click');
    $response->assertNoContent();
    expect($response->headers->getCookies())->toBe([]);

    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->post('/evenements-contenu/signup_click')->assertNoContent();
    $this->post('/evenements-contenu/inconnu')->assertNotFound();

    expect(App\Models\ContentEvent::query()->where('kind', 'signup_click')->value('hits'))->toBe(1);
});

it('masque par suppression complémentaire les indicateurs qui se somment : IND-01 = IND-02 + IND-03', function (): void {
    $pair = User::factory()->create();
    CaseRecord::factory()->vbg()->count(3)->create(['collected_by' => $pair->id, 'created_at' => '2026-09-05 10:00:00']);
    CaseRecord::factory()->vdh()->count(50)->create(['collected_by' => $pair->id, 'created_at' => '2026-09-06 10:00:00']);
    $rows = app(IndicatorBoard::class)->rows(septembre());

    // VBG = 3 est masqué ; 53 - 50 le retrouverait : VDH l'est aussi (seule leur somme reste déductible).
    expect(rowOf($rows, 'IND-02')->masked)->toBeTrue()
        ->and(rowOf($rows, 'IND-03')->masked)->toBeTrue()
        ->and(rowOf($rows, 'IND-01')->value)->toBe(53.0);

    // Groupe sans effectif de 1 à 4 : rien n'est masqué en plus.
    CaseRecord::factory()->vbg()->count(5)->create(['collected_by' => $pair->id, 'created_at' => '2026-09-07 10:00:00']);
    $rows = app(IndicatorBoard::class)->rows(septembre());
    expect(rowOf($rows, 'IND-02')->masked)->toBeFalse()->and(rowOf($rows, 'IND-03')->masked)->toBeFalse();
});

it('masque dans la tendance un pourcentage dont le dénominateur enregistré est inférieur à 5 ou inconnu', function (): void {
    $indicator = Indicator::query()->whereCode('IND-06')->firstOrFail();
    CaseRecord::factory()->vbg()->count(6)->create(['collected_by' => User::factory()->create()->id, 'created_at' => '2026-09-05 10:00:00']);

    foreach ([[4, 100, 1], [5, 50, 10], [6, 60, 4], [7, 70, null], [8, 80, 5], [9, 90, 20]] as [$month, $value, $base]) {
        IndicatorValue::record($indicator, Period::month(2026, $month)->start, Period::month(2026, $month)->end, null, $value, false, $base);
    }

    $row = rowOf(app(IndicatorBoard::class)->rows(septembre()), 'IND-06');

    // « 100 % » issu d'un seul dossier (avril), base de 4 (juin) et instantané sans dénominateur (juillet) ne sont pas tracés.
    expect(array_column($row->trend, 'value'))->toBe([null, 50.0, null, null, 80.0, 90.0]);
});

it('enregistre le dénominateur d’un pourcentage dans l’instantané mensuel', function (): void {
    $pair = User::factory()->create();
    CaseRecord::factory()->vbg()->count(6)->create(['collected_by' => $pair->id, 'created_at' => '2026-09-05 10:00:00']);

    app(App\Application\Steering\SnapshotIndicators::class)->run(septembre());

    $closed = IndicatorValue::query()->where('indicator_id', Indicator::query()->whereCode('IND-06')->value('id'))->whereNull('zone_id')->firstOrFail();
    $total = IndicatorValue::query()->where('indicator_id', Indicator::query()->whereCode('IND-01')->value('id'))->whereNull('zone_id')->firstOrFail();

    expect($closed->base)->toBe(6)->and($total->base)->toBeNull();
});

it('applique la suppression complémentaire à la tendance de IND-01, IND-02 et IND-03', function (): void {
    foreach ([['IND-01', 53], ['IND-02', 3], ['IND-03', 50]] as [$code, $value]) {
        IndicatorValue::record(Indicator::query()->whereCode($code)->firstOrFail(), Period::month(2026, 9)->start, Period::month(2026, 9)->end, null, $value);
    }

    $rows = app(IndicatorBoard::class)->rows(septembre());

    // La trace de septembre : total 53 affiché, VBG (3) et VDH (50, complément) retirés de la courbe.
    expect(rowOf($rows, 'IND-01')->trend[5]['value'])->toBe(53.0)
        ->and(rowOf($rows, 'IND-02')->trend)->toBe([])
        ->and(rowOf($rows, 'IND-03')->trend)->toBe([]);
});

it('calcule IND-04 sur la date locale et sur les seules étapes réalisées', function (): void {
    $pair = User::factory()->create();
    $zone = Zone::factory()->create();
    // 05/09 23 h 30 UTC = 06/09 00 h 30 à Porto-Novo : limite locale le 09/09 (inclus), alors que la date UTC donnerait le 08/09.
    $midnight = CaseRecord::factory()->vbg()->create(['zone_id' => $zone->id, 'collected_by' => $pair->id, 'created_at' => '2026-09-05 23:30:00']);
    // Étape planifiée à temps, jamais réalisée : pas une prise en charge.
    $planned = CaseRecord::factory()->vbg()->create(['zone_id' => $zone->id, 'collected_by' => $pair->id, 'created_at' => '2026-09-05 10:00:00']);

    CaseFollowup::factory()->done()->forCase($midnight)->create(['date' => '2026-09-09']);
    CaseFollowup::factory()->forCase($planned)->create(['date' => '2026-09-06', 'outcome' => App\Enums\FollowupOutcome::Planned]);

    $result = app(IndicatorCalculator::class)->compute('pct_72h', septembre());

    expect($result->value)->toBe(50.0)->and($result->base)->toBe(2);
});

it('ne compte pas un événement de contenu venu d’un autre site, et limite la balise par adresse', function (): void {
    config(['xomiton.rate_limits.content_event_per_minute' => 4]);
    $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])->post('/evenements-contenu/signup_click')->assertNoContent();
    $this->withHeaders(['Origin' => 'https://autre-site.invalid'])->post('/evenements-contenu/signup_click')->assertNoContent();
    expect(App\Models\ContentEvent::query()->count())->toBe(0);

    $this->withHeaders(['Sec-Fetch-Site' => 'same-origin', 'Origin' => 'http://localhost'])->post('/evenements-contenu/signup_click')->assertNoContent();
    expect(App\Models\ContentEvent::query()->where('kind', 'signup_click')->value('hits'))->toBe(1);

    expect(config('xomiton.rate_limits.admin_search_per_minute'))->toBe(60)
        ->and(Illuminate\Support\Facades\RateLimiter::limiter('content-event'))->not->toBeNull()
        ->and(Illuminate\Support\Facades\RateLimiter::limiter('admin-search'))->not->toBeNull();

    // Trois requêtes déjà comptées sur 4 par minute.
    $this->post('/evenements-contenu/download')->assertNoContent();
    $this->post('/evenements-contenu/download')->assertStatus(429);
});
