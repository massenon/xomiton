<?php

declare(strict_types=1);

use App\Enums\IndicatorFrequency;
use App\Enums\JobStatus;
use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportKind;
use App\Models\Comment;
use App\Models\GeneratedReport;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Models\UserWarning;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Database\Seeders\IndicatorsSeeder;
use Illuminate\Database\QueryException;

/*
 * Lot 8 « steering » : une création par factory et une assertion métier par table
 * (DATABASE_DICTIONARY.md §7). Nécessite l'intégration des lots pour RefreshDatabase.
 */

describe('indicators', function (): void {
    it('crée un indicateur avec un code unique, une fréquence castée et une cible comparée selon le sens souhaité', function (): void {
        $indicator = Indicator::factory()->percent()->frequency(IndicatorFrequency::Monthly)->create(['target' => 80]);
        $down = Indicator::factory()->betterDown()->create(['target' => 0]);
        Indicator::factory()->manual()->create();
        Indicator::factory()->inactive()->create();

        expect($indicator->frequency)->toBe(IndicatorFrequency::Monthly)
            ->and($indicator->frequency->label())->toBe('Mensuelle')
            ->and($indicator->isPercentage())->toBeTrue()
            ->and($indicator->meetsTarget(85.0))->toBeTrue()
            ->and($indicator->meetsTarget(70.0))->toBeFalse()
            ->and($down->meetsTarget(0.0))->toBeTrue()
            ->and($down->meetsTarget(2.0))->toBeFalse()
            ->and(Indicator::factory()->withoutTarget()->create()->meetsTarget(1.0))->toBeNull()
            ->and(Indicator::query()->manual()->count())->toBe(1)
            ->and(Indicator::query()->computed()->count())->toBe(4)
            ->and(Indicator::query()->active()->count())->toBe(4)
            ->and(Indicator::query()->whereCode(strtolower($indicator->code))->exists())->toBeTrue();

        expect(fn () => Indicator::factory()->create(['code' => $indicator->code]))->toThrow(QueryException::class);
    });

    it('seede les 16 indicateurs du prototype de façon idempotente, IND-09 étant la newsletter', function (): void {
        $this->seed(IndicatorsSeeder::class);
        Indicator::query()->whereCode('IND-01')->update(['is_active' => false, 'label' => 'modifié']);
        $this->seed(IndicatorsSeeder::class);

        $newsletter = Indicator::query()->whereCode('IND-09')->firstOrFail();
        $first = Indicator::query()->whereCode('IND-01')->firstOrFail();

        expect(Indicator::query()->count())->toBe(16)
            ->and(Indicator::query()->builtin()->count())->toBe(16)
            ->and($newsletter->calc_key)->toBe('nl_open')
            ->and($newsletter->source)->toBe('Newsletter')
            ->and($newsletter->unit)->toBe(Indicator::UNIT_PERCENT)
            ->and((float) $newsletter->target)->toBe(35.0)
            ->and($first->label)->toBe('Cas enregistrés (VBG + VDH)')
            ->and($first->is_active)->toBeFalse()
            ->and(Indicator::query()->whereCode('IND-12')->firstOrFail()->better)->toBe(Indicator::BETTER_DOWN)
            ->and(Indicator::query()->whereCode('IND-10')->firstOrFail()->frequency)->toBe(IndicatorFrequency::Weekly)
            ->and(IndicatorFrequency::fromPrototype('Semestrielle'))->toBe(IndicatorFrequency::Semiannual)
            ->and(IndicatorFrequency::fromPrototype('Annuelle')->label())->toBe('Annuelle')
            ->and(Indicator::query()->ordered()->pluck('code')->first())->toBe('IND-01');
    });
});

describe('indicator_values', function (): void {
    it('stocke une valeur par indicateur, période et zone, horodate le calcul et refuse le doublon', function (): void {
        $indicator = Indicator::factory()->create();
        $zone = Zone::factory()->create();
        $national = IndicatorValue::factory()->for($indicator)->forMonth(2026, 3)->value(42.5)->create(['computed_at' => null]);
        $zoned = IndicatorValue::factory()->for($indicator)->forMonth(2026, 3)->forZone($zone)->manual()->create();
        IndicatorValue::factory()->for($indicator)->forMonth(2026, 4)->create();

        expect($national->computed_at)->not->toBeNull()
            ->and($national->isNational())->toBeTrue()
            ->and((float) $national->value)->toBe(42.5)
            ->and($national->period_start->toDateString())->toBe('2026-03-01')
            ->and($national->period_end->toDateString())->toBe('2026-03-31')
            ->and($zoned->is_manual)->toBeTrue()
            ->and($zoned->zone->is($zone))->toBeTrue()
            ->and($indicator->values()->count())->toBe(3)
            ->and(IndicatorValue::query()->national()->count())->toBe(2)
            ->and(IndicatorValue::query()->manual()->count())->toBe(1)
            ->and(IndicatorValue::query()->forZone($zone)->count())->toBe(1)
            ->and(IndicatorValue::query()->forPeriod(CarbonImmutable::create(2026, 3, 1), CarbonImmutable::create(2026, 3, 31))->count())->toBe(2)
            ->and(IndicatorValue::query()->between(CarbonImmutable::create(2026, 3, 15), CarbonImmutable::create(2026, 4, 15))->count())->toBe(3);

        expect(fn () => IndicatorValue::factory()->for($indicator)->forMonth(2026, 3)->forZone($zone)->create())->toThrow(QueryException::class);

        // Valeur nationale : l'unicité SQL ne joue pas (zone_id null), record() met à jour la ligne existante.
        $recorded = IndicatorValue::record($indicator, CarbonImmutable::create(2026, 3, 1), CarbonImmutable::create(2026, 3, 31), null, 50);

        expect($recorded->is($national))->toBeTrue()
            ->and((float) $recorded->value)->toBe(50.0)
            ->and(IndicatorValue::query()->national()->count())->toBe(2)
            ->and(IndicatorValue::record($indicator, CarbonImmutable::create(2026, 5, 1), CarbonImmutable::create(2026, 5, 31), $zone, 7, true)->is_manual)->toBeTrue()
            ->and(IndicatorValue::query()->count())->toBe(4);

        $indicator->delete();

        expect(IndicatorValue::query()->count())->toBe(0);
    });
});

describe('generated_reports', function (): void {
    it('crée un rapport en attente puis le marque terminé avec un fichier, et retient ceux expirés', function (): void {
        $pending = GeneratedReport::factory()->create();
        $done = GeneratedReport::factory()->done(ReportFormat::Xlsx)->create();
        GeneratedReport::factory()->expired()->create();
        GeneratedReport::factory()->failed()->create();
        GeneratedReport::factory()->running()->ofKind(ReportKind::Donor)->create();

        expect($pending->status)->toBe(JobStatus::Pending)
            ->and($pending->status->label())->toBe('En attente')
            ->and($pending->kind)->toBe(ReportKind::Annual)
            ->and($pending->format)->toBe(ReportFormat::Preview)
            ->and($pending->format->producesFile())->toBeFalse()
            ->and($pending->dimensions)->toBe(['departement', 'sexe', 'age'])
            ->and($pending->periodLabel())->toMatch('#^01/01/\d{4} – 31/12/\d{4}$#')
            ->and($pending->generatedBy)->toBeInstanceOf(User::class)
            ->and($done->hasFile())->toBeTrue()
            ->and($done->file_path)->toEndWith('.xlsx')
            ->and($done->isFinished())->toBeTrue()
            ->and(GeneratedReport::query()->unfinished()->count())->toBe(2)
            ->and(GeneratedReport::query()->expired()->count())->toBe(1)
            ->and(GeneratedReport::query()->downloadable()->pluck('id')->all())->toBe([$done->id])
            ->and(GeneratedReport::query()->ofKind(ReportKind::Donor)->count())->toBe(1);

        $pending->generatedBy->delete();
        $pending->generatedBy->forceDelete();

        expect($pending->fresh()->generated_by)->toBeNull();
    });
});

describe('report_schedules', function (): void {
    it('planifie un rapport mensuel, calcule la prochaine échéance et sélectionne les planifications dues', function (): void {
        $schedule = ReportSchedule::factory()->formats([ReportFormat::Pdf, ReportFormat::Xlsx])->create(['day_of_period' => 5]);
        $schedule->refresh();
        $due = ReportSchedule::factory()->quarterly()->due()->create();
        $semiannual = ReportSchedule::factory()->semiannual()->create(['day_of_period' => 15]);
        ReportSchedule::factory()->annual()->paused()->due()->create();

        $next = $schedule->computeNextRunAt(CarbonImmutable::create(2026, 3, 10, 9));
        $nextQuarter = $due->computeNextRunAt(CarbonImmutable::create(2026, 3, 10));

        expect($schedule->kind)->toBe(ReportKind::Monthly)
            ->and($schedule->kind->isPeriodic())->toBeTrue()
            ->and($schedule->frequency)->toBe(ReportSchedule::FREQUENCY_MONTHLY)
            ->and($schedule->formatList())->toBe([ReportFormat::Pdf, ReportFormat::Xlsx])
            ->and($schedule->recipients)->toHaveCount(2)
            ->and($schedule->is_active)->toBeTrue()
            ->and($next->toDateTimeString())->toBe('2026-04-05 00:00:00')
            ->and($schedule->next_run_at->isFuture())->toBeTrue()
            ->and($schedule->next_run_at->day)->toBe(5)
            ->and($nextQuarter->toDateTimeString())->toBe('2026-04-'.str_pad((string) $due->day_of_period, 2, '0', STR_PAD_LEFT).' 00:00:00')
            ->and($semiannual->computeNextRunAt(CarbonImmutable::create(2026, 3, 10))->toDateTimeString())->toBe('2026-07-15 00:00:00')
            ->and($semiannual->computeNextRunAt(CarbonImmutable::create(2026, 7, 15))->toDateTimeString())->toBe('2027-01-15 00:00:00')
            ->and($semiannual->kind)->toBe(ReportKind::Semiannual)
            ->and(ReportSchedule::query()->due()->pluck('id')->all())->toBe([$due->id])
            ->and(ReportSchedule::query()->active()->count())->toBe(3)
            ->and(ReportSchedule::query()->ofKind(ReportKind::Annual)->count())->toBe(1);
    });
});

describe('moderation_reports', function (): void {
    it('cible un contenu polymorphe, caste nature et statut, et liste les signalements en attente', function (): void {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create();
        $reporter = User::factory()->create();
        $report = ModerationReport::factory()->forSubject($post)->reportedBy($reporter)->create();
        ModerationReport::factory()->forSubject($post)->bySystem()->create();
        $onComment = ModerationReport::factory()->forSubject($comment)->handled(ModerationReport::ACTION_HIDE)->create();
        $onUser = ModerationReport::factory()->onUser()->rejected()->create();

        expect($report->kind)->toBe(ModerationKind::Post)
            ->and($report->kind->label())->toBe('Publication')
            ->and($report->status)->toBe(ModerationStatus::Pending)
            ->and($report->status->badge())->toBe('warning')
            ->and($report->subject->is($post))->toBeTrue()
            ->and($report->reported_user_id)->toBe($post->user_id)
            ->and($report->reporterLabel())->toBe($reporter->pseudo)
            ->and($report->isPending())->toBeTrue()
            ->and($onComment->kind)->toBe(ModerationKind::Comment)
            ->and($onComment->action)->toBe(ModerationReport::ACTION_HIDE)
            ->and($onComment->handledBy)->toBeInstanceOf(User::class)
            ->and($onUser->kind)->toBe(ModerationKind::User)
            ->and($onUser->subject->is($onUser->reportedUser))->toBeTrue()
            ->and(ModerationReport::query()->pending()->count())->toBe(2)
            ->and(ModerationReport::query()->closed()->count())->toBe(2)
            ->and(ModerationReport::query()->forSubject($post)->count())->toBe(2)
            ->and(ModerationReport::query()->againstUser($onUser->reportedUser)->count())->toBe(1);
    });

    it('conserve le signalement quand le compte signalé est supprimé (nullOnDelete)', function (): void {
        $report = ModerationReport::factory()->create();
        $reported = $report->reportedUser;

        $reported->delete();
        $reported->forceDelete();

        expect($report->fresh())->not->toBeNull()
            ->and($report->fresh()->reported_user_id)->toBeNull()
            ->and(ModerationReport::factory()->bySystem()->create()->reporterLabel())->toBe('Système (mots interdits)');
    });
});

describe('user_warnings', function (): void {
    it('journalise un avertissement sans updated_at, lié au signalement, et disparaît avec le compte', function (): void {
        $member = User::factory()->create();
        $warning = UserWarning::factory()->forUser($member)->daysAgo(10)->create();
        $fromReport = UserWarning::factory()->fromReport()->create();
        UserWarning::factory()->forUser($member)->automatic()->create();

        expect(UserWarning::UPDATED_AT)->toBeNull()
            ->and($warning->created_at)->not->toBeNull()
            ->and($warning->user->is($member))->toBeTrue()
            ->and($warning->issuedBy)->toBeInstanceOf(User::class)
            ->and($fromReport->moderationReport->action)->toBe(ModerationReport::ACTION_WARN)
            ->and($fromReport->user_id)->toBe($fromReport->moderationReport->reported_user_id)
            ->and($fromReport->moderationReport->warnings()->count())->toBe(1)
            ->and(UserWarning::query()->forUser($member)->count())->toBe(2)
            ->and(UserWarning::query()->forUser($member)->since(now()->subDays(3))->count())->toBe(1)
            ->and(UserWarning::query()->forUser($member)->chronological()->pluck('id')->first())->toBe($warning->id);

        $member->forceDelete();

        expect(UserWarning::query()->forUser($member->id)->count())->toBe(0)
            ->and(UserWarning::query()->count())->toBe(1);
    });
});
