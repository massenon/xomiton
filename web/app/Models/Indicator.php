<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IndicatorFrequency;
use Database\Factories\IndicatorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Indicateur de pilotage (DATABASE_DICTIONARY.md §7 `indicators`). Les indicateurs intégrés (`is_builtin`)
 * sont calculés par `calc_key` ; un indicateur sans `calc_key` est alimenté à la main dans `indicator_values`.
 */
class Indicator extends Model
{
    /** @use HasFactory<IndicatorFactory> */
    use HasFactory;

    /** Sens souhaité de l'évolution (`better`). */
    public const BETTER_UP = 'up';

    public const BETTER_DOWN = 'down';

    /** Unités admises (`unit`). */
    public const UNIT_COUNT = 'nb';

    public const UNIT_PERCENT = '%';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'label',
        'definition',
        'formula',
        'source',
        'frequency',
        'target',
        'unit',
        'calc_key',
        'better',
        'is_builtin',
        'is_active',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => IndicatorFrequency::class,
            'target' => 'decimal:2',
            'is_builtin' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<IndicatorValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }

    /**
     * @param  Builder<Indicator>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Indicator>  $query
     */
    public function scopeBuiltin(Builder $query): void
    {
        $query->where('is_builtin', true);
    }

    /**
     * Indicateurs saisis à la main (sans clé de calcul).
     *
     * @param  Builder<Indicator>  $query
     */
    public function scopeManual(Builder $query): void
    {
        $query->whereNull('calc_key');
    }

    /**
     * Indicateurs calculés automatiquement (`indicators:snapshot`).
     *
     * @param  Builder<Indicator>  $query
     */
    public function scopeComputed(Builder $query): void
    {
        $query->whereNotNull('calc_key');
    }

    /**
     * @param  Builder<Indicator>  $query
     */
    public function scopeOfFrequency(Builder $query, IndicatorFrequency $frequency): void
    {
        $query->where('frequency', $frequency);
    }

    /**
     * @param  Builder<Indicator>  $query
     */
    public function scopeWhereCode(Builder $query, string $code): void
    {
        $query->where('code', strtoupper(trim($code)));
    }

    /**
     * @param  Builder<Indicator>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('code');
    }

    public function isManual(): bool
    {
        return $this->calc_key === null;
    }

    public function isPercentage(): bool
    {
        return $this->unit === self::UNIT_PERCENT;
    }

    /**
     * La valeur atteint-elle la cible, selon le sens souhaité ? Null sans cible.
     */
    public function meetsTarget(float $value): ?bool
    {
        if ($this->target === null) {
            return null;
        }

        $target = (float) $this->target;

        return $this->better === self::BETTER_DOWN ? $value <= $target : $value >= $target;
    }
}
