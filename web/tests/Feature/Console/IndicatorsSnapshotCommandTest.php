<?php

declare(strict_types=1);

use App\Application\Steering\IndicatorCalculator;
use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\Event;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Database\Seeders\IndicatorsSeeder;
use Illuminate\Support\Carbon;

/*
 * `indicators:snapshot` (P1-16) : instantané du mois précédent pour chaque indicateur intégré actif doté d'une clé
 * de calcul, national puis par département ; idempotent ; valeurs manuelles conservées ; IND-04 (premier suivi ≤ création + 72 h) calculé.
 */

function valueOf(string $code, ?Zone $zone = null): ?float
{
    $indicator = Indicator::query()->whereCode($code)->firstOrFail();

    $row = IndicatorValue::query()
        ->where('indicator_id', $indicator->getKey())
        ->where('zone_id', $zone?->getKey())
        ->forPeriod(CarbonImmutable::create(2026, 9, 1), CarbonImmutable::create(2026, 9, 30))
        ->first();

    return $row === null ? null : (float) $row->value;
}

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 4));
    $this->seed(IndicatorsSeeder::class);
});

it('couvre chaque clé de calcul du catalogue : calculable ou non calculable avec une raison', function (): void {
    $calculator = app(IndicatorCalculator::class);

    foreach (Indicator::query()->computed()->pluck('calc_key') as $key) {
        if ($calculator->supports($key)) {
            continue;
        }

        expect($calculator->unavailableReason($key))->not->toContain('inconnue', "clé {$key}");
    }

    expect($calculator->supports('pct_72h'))->toBeTrue()
        ->and($calculator->isZonal('pct_72h'))->toBeTrue()
        ->and($calculator->supports('cas_total'))->toBeTrue()
        ->and($calculator->isZonal('cas_total'))->toBeTrue()
        ->and($calculator->isZonal('couverture'))->toBeFalse()
        ->and(count(IndicatorCalculator::keys()))->toBe(16);
});

it('calcule le mois précédent au niveau national et par département, de façon idempotente', function (): void {
    $zoneA = Zone::factory()->create(['name' => 'Atlantique', 'position' => 1]);
    $zoneB = Zone::factory()->create(['name' => 'Borgou', 'position' => 2]);
    $pairX = User::factory()->create();
    $pairY = User::factory()->create();

    // Dossiers enregistrés en septembre (c3 archivé, c5 et c6 hors période).
    $c1 = CaseRecord::factory()->vbg()->create(['zone_id' => $zoneA->id, 'collected_by' => $pairX->id, 'referral' => 'Juriste', 'created_at' => '2026-09-05 10:00:00']);
    $c2 = CaseRecord::factory()->vbg()->closed()->create(['zone_id' => $zoneA->id, 'collected_by' => $pairX->id, 'referral' => null, 'created_at' => '2026-09-10 10:00:00']);
    CaseRecord::factory()->vdh()->archived()->create(['zone_id' => $zoneA->id, 'collected_by' => $pairY->id, 'referral' => null, 'created_at' => '2026-09-20 10:00:00']);
    $c4 = CaseRecord::factory()->vbg()->create(['zone_id' => $zoneB->id, 'collected_by' => $pairY->id, 'referral' => null, 'created_at' => '2026-09-25 10:00:00']);
    CaseRecord::factory()->vbg()->create(['zone_id' => $zoneA->id, 'collected_by' => $pairX->id, 'created_at' => '2026-10-01 00:30:00']);
    CaseRecord::factory()->vbg()->create(['zone_id' => $zoneA->id, 'collected_by' => $pairX->id, 'created_at' => '2026-08-31 23:00:00']);

    expect($c2->status)->toBe(CaseStatus::Closed);

    // Alertes de septembre dans l'Atlantique : a1 traitée en 10 h, a2 close après 2 jours, a3 SOS annulé, a4 nouvelle.
    $a1 = Alert::factory()->withStatus(AlertStatus::Handled)->create(['zone_id' => $zoneA->id, 'created_at' => '2026-09-02 10:00:00']);
    (new AlertStatusChange)->forceFill(['alert_id' => $a1->id, 'from_status' => AlertStatus::Acknowledged, 'to_status' => AlertStatus::Handled, 'created_at' => '2026-09-02 20:00:00'])->save();
    Alert::factory()->withStatus(AlertStatus::Closed)->create(['zone_id' => $zoneA->id, 'created_at' => '2026-09-03 10:00:00', 'closed_at' => '2026-09-05 10:00:00']);
    Alert::factory()->sos()->cancelled()->create(['zone_id' => $zoneA->id, 'created_at' => '2026-09-04 10:00:00']);
    Alert::factory()->create(['zone_id' => $zoneA->id, 'created_at' => '2026-09-06 10:00:00']);
    Alert::factory()->create(['zone_id' => $zoneA->id, 'created_at' => '2026-10-01 01:00:00']);

    // Suivis datés de septembre (f4 en octobre).
    CaseFollowup::factory()->done()->forCase($c1)->create(['date' => '2026-09-07']);
    CaseFollowup::factory()->planned()->forCase($c1)->create(['date' => '2026-09-14']);
    CaseFollowup::factory()->done()->forCase($c4)->create(['date' => '2026-09-21']);
    CaseFollowup::factory()->done()->forCase($c1)->create(['date' => '2026-10-03']);

    // Événements : un publié en septembre, un brouillon, un en octobre.
    Event::factory()->create(['starts_at' => '2026-09-12 15:00:00', 'ends_at' => '2026-09-12 17:00:00']);
    Event::factory()->draft()->create(['starts_at' => '2026-09-13 15:00:00', 'ends_at' => '2026-09-13 17:00:00']);
    Event::factory()->create(['starts_at' => '2026-10-02 15:00:00', 'ends_at' => '2026-10-02 17:00:00']);

    // Newsletter : campagne envoyée le 15/09, 3 e-mails délivrés dont 1 ouvert, 1 rebond.
    $campaign = NewsletterCampaign::factory()->sent()->create(['sent_at' => '2026-09-15 09:00:00']);
    NewsletterCampaignRecipient::factory()->forCampaign($campaign)->create(['sent_at' => '2026-09-15 09:01:00', 'opened_at' => '2026-09-15 12:00:00']);
    NewsletterCampaignRecipient::factory()->forCampaign($campaign)->create(['sent_at' => '2026-09-15 09:01:00']);
    NewsletterCampaignRecipient::factory()->forCampaign($campaign)->create(['sent_at' => '2026-09-15 09:01:00']);
    NewsletterCampaignRecipient::factory()->forCampaign($campaign)->create(['sent_at' => '2026-09-15 09:01:00', 'bounced' => true]);

    // Valeur manuelle déjà saisie pour IND-01 (national) : conservée.
    IndicatorValue::factory()->manual()->forMonth(2026, 9)->create([
        'indicator_id' => Indicator::query()->whereCode('IND-01')->firstOrFail()->id,
        'zone_id' => null,
        'value' => 999,
    ]);

    $this->artisan('indicators:snapshot')
        ->expectsOutputToContain('Instantané 09/2026 : 40 valeurs enregistrées (16 indicateurs, 2 départements), 1 calcul sans donnée, 1 valeur manuelle conservée.')
        ->assertSuccessful();

    expect(valueOf('IND-01'))->toBe(999.0)
        ->and(valueOf('IND-01', $zoneA))->toBe(2.0)
        ->and(valueOf('IND-01', $zoneB))->toBe(1.0)
        ->and(valueOf('IND-02'))->toBe(3.0)
        ->and(valueOf('IND-03'))->toBe(0.0)
        ->and(valueOf('IND-04'))->toBe(50.0)
        ->and(valueOf('IND-04', $zoneA))->toBe(33.33)
        ->and(valueOf('IND-04', $zoneB))->toBe(100.0)
        ->and(valueOf('IND-05'))->toBe(25.0)
        ->and(valueOf('IND-05', $zoneA))->toBe(33.33)
        ->and(valueOf('IND-06'))->toBe(50.0)
        ->and(valueOf('IND-06', $zoneA))->toBe(66.67)
        ->and(valueOf('IND-07'))->toBe(2.0)
        ->and(valueOf('IND-07', $zoneB))->toBe(1.0)
        ->and(valueOf('IND-08'))->toBe(1.0)
        ->and(valueOf('IND-08', $zoneA))->toBeNull()
        ->and(valueOf('IND-09'))->toBe(33.33)
        ->and(valueOf('IND-10'))->toBe(33.33)
        ->and(valueOf('IND-10', $zoneB))->toBeNull()
        ->and(valueOf('IND-11'))->toBe(4.0)
        ->and(valueOf('IND-12'))->toBe(1.0)
        ->and(valueOf('IND-12', $zoneB))->toBe(0.0)
        ->and(valueOf('IND-13'))->toBe(66.67)
        ->and(valueOf('IND-13', $zoneA))->toBe(50.0)
        ->and(valueOf('IND-14'))->toBe(2.0)
        ->and(valueOf('IND-14', $zoneA))->toBe(1.0)
        ->and(valueOf('IND-15'))->toBe((float) User::query()->active()->count())
        ->and(valueOf('IND-16'))->toBe(100.0)
        ->and(valueOf('IND-16', $zoneA))->toBeNull();

    $rows = IndicatorValue::query()->count();

    expect(IndicatorValue::query()->computed()->count())->toBe(40)
        ->and(IndicatorValue::query()->manual()->count())->toBe(1);

    $this->artisan('indicators:snapshot', ['--month' => '2026-09'])->assertSuccessful();

    expect(IndicatorValue::query()->count())->toBe($rows)
        ->and(valueOf('IND-01'))->toBe(999.0)
        ->and(valueOf('IND-11'))->toBe(4.0);
});

it('ignore les indicateurs inactifs ou manuels et refuse un mois mal formé', function (): void {
    Indicator::query()->whereCode('IND-11')->update(['is_active' => false]);
    Indicator::factory()->manual()->create();
    Alert::factory()->create(['created_at' => '2026-09-06 10:00:00']);

    $this->artisan('indicators:snapshot', ['--month' => '2026-09'])->assertSuccessful();

    expect(valueOf('IND-11'))->toBeNull()
        ->and(valueOf('IND-12'))->toBe(0.0)
        ->and(IndicatorValue::query()->whereHas('indicator', fn ($q) => $q->manual())->exists())->toBeFalse();

    $this->artisan('indicators:snapshot', ['--month' => '09/2026'])->assertFailed();
    $this->artisan('indicators:snapshot', ['--month' => '2026-13'])->assertFailed();
});
