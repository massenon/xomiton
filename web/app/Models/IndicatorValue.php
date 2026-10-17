<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\IndicatorValueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valeur d'un indicateur sur une période, nationale (`zone_id` null) ou par département
 * (DATABASE_DICTIONARY.md §7 `indicator_values`). Saisie manuelle ou instantané calculé.
 */
class IndicatorValue extends Model
{
    /** @use HasFactory<IndicatorValueFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'indicator_id',
        'period_start',
        'period_end',
        'zone_id',
        'value',
        'is_manual',
        'computed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'value' => 'decimal:2',
            'is_manual' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // L'horodatage du calcul est obligatoire : il vaut l'instant de création par défaut.
        static::creating(function (IndicatorValue $value): void {
            if ($value->computed_at === null) {
                $value->computed_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<Indicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @param  Builder<IndicatorValue>  $query
     */
    public function scopeManual(Builder $query): void
    {
        $query->where('is_manual', true);
    }

    /**
     * @param  Builder<IndicatorValue>  $query
     */
    public function scopeComputed(Builder $query): void
    {
        $query->where('is_manual', false);
    }

    /**
     * Valeurs nationales (toutes zones confondues).
     *
     * @param  Builder<IndicatorValue>  $query
     */
    public function scopeNational(Builder $query): void
    {
        $query->whereNull('zone_id');
    }

    /**
     * @param  Builder<IndicatorValue>  $query
     */
    public function scopeForZone(Builder $query, Zone|int $zone): void
    {
        $query->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone);
    }

    /**
     * Valeurs dont la période est exactement celle demandée.
     *
     * @param  Builder<IndicatorValue>  $query
     */
    public function scopeForPeriod(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->whereDate('period_start', $start->toDateString())
            ->whereDate('period_end', $end->toDateString());
    }

    /**
     * Valeurs dont la période recoupe l'intervalle demandé (courbes du tableau de bord).
     *
     * @param  Builder<IndicatorValue>  $query
     */
    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereDate('period_end', '>=', $from->toDateString())
            ->whereDate('period_start', '<=', $to->toDateString());
    }

    /**
     * @param  Builder<IndicatorValue>  $query
     */
    public function scopeChronological(Builder $query): void
    {
        $query->orderBy('period_start')->orderBy('id');
    }

    public function isNational(): bool
    {
        return $this->zone_id === null;
    }

    /**
     * Enregistre ou met à jour la valeur d'une période (point d'entrée de `indicators:snapshot` et de la
     * saisie manuelle) : l'unicité SQL ne couvre pas les valeurs nationales (`zone_id` null, NULL distincts),
     * c'est donc ici que le quadruplet est garanti unique.
     */
    public static function record(
        Indicator|int $indicator,
        CarbonInterface $start,
        CarbonInterface $end,
        Zone|int|null $zone,
        float|int|string $value,
        bool $manual = false,
    ): self {
        $indicatorId = $indicator instanceof Indicator ? $indicator->getKey() : $indicator;
        $zoneId = $zone instanceof Zone ? $zone->getKey() : $zone;

        $row = static::query()
            ->where('indicator_id', $indicatorId)
            ->where('zone_id', $zoneId)
            ->forPeriod($start, $end)
            ->first();

        $row ??= new static([
            'indicator_id' => $indicatorId,
            'zone_id' => $zoneId,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ]);

        $row->fill(['value' => $value, 'is_manual' => $manual, 'computed_at' => now()])->save();

        return $row;
    }
}
