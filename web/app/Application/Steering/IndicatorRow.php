<?php

declare(strict_types=1);

namespace App\Application\Steering;

use App\Models\Indicator;

/**
 * Ligne de l'écran Indicateurs : un indicateur, sa valeur sur la période, son avancement vers la cible et sa tendance
 * (instantanés mensuels). `masked` : effectif de 1 à 4 (valeur, ou dénominateur d'un pourcentage), jamais affiché ; la
 * barre de progression et la tendance sont alors retirées pour ne pas le déduire.
 */
final readonly class IndicatorRow
{
    public const REACHED = 'reached';

    public const PROGRESS = 'progress';

    public const LOW = 'low';

    /** Aucune valeur (pas de donnée sur la période, valeur manuelle absente, indicateur national seul en filtre de zone). */
    public const NONE = 'none';

    /** Valeur masquée (effectif de 1 à 4). */
    public const MASKED = 'masked';

    /**
     * @param  list<array{label: string, value: float|null}>  $trend  six derniers instantanés mensuels, du plus ancien au plus récent
     */
    public function __construct(
        public Indicator $indicator,
        public ?float $value,
        public bool $masked,
        public ?string $reason,
        public ?float $progress,
        public string $status,
        public array $trend,
        public ?string $trendDirection,
    ) {}

    public function hasValue(): bool
    {
        return $this->value !== null && ! $this->masked;
    }
}
