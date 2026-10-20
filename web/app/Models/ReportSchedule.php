<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportFormat;
use App\Enums\ReportKind;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ReportScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Planification de rapports automatiques (DATABASE_DICTIONARY.md §7 `report_schedules`) : à chaque période,
 * le jour `day_of_period`, un rapport est généré dans chaque format puis envoyé aux destinataires.
 */
class ReportSchedule extends Model
{
    /** @use HasFactory<ReportScheduleFactory> */
    use HasFactory;

    /** Fréquences admises (`frequency`) : le dictionnaire ne prévoit pas d'enum dédié. */
    public const FREQUENCY_WEEKLY = 'hebdomadaire';

    public const FREQUENCY_MONTHLY = 'mensuel';

    public const FREQUENCY_QUARTERLY = 'trimestriel';

    public const FREQUENCY_SEMIANNUAL = 'semestriel';

    public const FREQUENCY_ANNUAL = 'annuel';

    /** @var list<string> */
    public const FREQUENCIES = [self::FREQUENCY_WEEKLY, self::FREQUENCY_MONTHLY, self::FREQUENCY_QUARTERLY, self::FREQUENCY_SEMIANNUAL, self::FREQUENCY_ANNUAL];

    /** Jour d'envoi maximal : existe dans tous les mois (prototype : 1–28). */
    public const MAX_DAY_OF_PERIOD = 28;

    /** Fréquence hebdomadaire : `day_of_period` est le jour de la semaine (1 = lundi … 7 = dimanche). */
    public const MAX_WEEKDAY = 7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'frequency',
        'day_of_period',
        'formats',
        'recipients',
        'dimensions',
        'is_active',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReportKind::class,
            'day_of_period' => 'integer',
            'formats' => 'array',
            'recipients' => 'array',
            'dimensions' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<ReportSchedule>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Planifications actives dont l'échéance est atteinte (tâche planifiée `reports:run-schedules`).
     *
     * @param  Builder<ReportSchedule>  $query
     */
    public function scopeDue(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->active()->whereNotNull('next_run_at')->where('next_run_at', '<=', $at ?? now());
    }

    /**
     * @param  Builder<ReportSchedule>  $query
     */
    public function scopeOfKind(Builder $query, ReportKind $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * Formats demandés, castés en enum (les valeurs inconnues sont ignorées).
     *
     * @return list<ReportFormat>
     */
    public function formatList(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $value): ?ReportFormat => is_string($value) ? ReportFormat::tryFrom($value) : null,
            $this->formats ?? [],
        )));
    }

    /**
     * Prochaine échéance strictement postérieure à `$from` : le jour `day_of_period` du début de la
     * prochaine période (mois, trimestre, semestre ou année), à minuit.
     */
    public function computeNextRunAt(?CarbonInterface $from = null): CarbonImmutable
    {
        $from = CarbonImmutable::instance($from ?? now());

        if ($this->frequency === self::FREQUENCY_WEEKLY) {
            $weekday = max(1, min(self::MAX_WEEKDAY, $this->day_of_period));
            $candidate = $from->startOfWeek()->addDays($weekday - 1)->startOfDay();

            return $candidate->lessThanOrEqualTo($from) ? $candidate->addWeek() : $candidate;
        }

        $day = max(1, min(self::MAX_DAY_OF_PERIOD, $this->day_of_period));
        $months = $this->periodMonths();

        // Début de la période courante : le mois aligné sur un multiple de la longueur de période.
        $candidate = $from->startOfYear()->addMonths(intdiv($from->month - 1, $months) * $months)->setDay($day)->startOfDay();

        while ($candidate->lessThanOrEqualTo($from)) {
            $candidate = $candidate->startOfMonth()->addMonths($months)->setDay($day)->startOfDay();
        }

        return $candidate;
    }

    /** Longueur de la période en mois selon la fréquence. */
    public function periodMonths(): int
    {
        return match ($this->frequency) {
            self::FREQUENCY_QUARTERLY => 3,
            self::FREQUENCY_SEMIANNUAL => 6,
            self::FREQUENCY_ANNUAL => 12,
            default => 1,
        };
    }
}
