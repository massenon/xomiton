<?php

declare(strict_types=1);

namespace App\Application\Steering;

/**
 * Résultat d'un calcul d'indicateur : une valeur, ou l'absence de valeur avec sa raison (dénominateur nul,
 * données manquantes…). Une valeur absente n'est pas enregistrée dans `indicator_values`.
 */
final readonly class IndicatorComputation
{
    private function __construct(
        public ?float $value,
        public ?string $reason,
    ) {}

    public static function of(float|int $value): self
    {
        return new self(round((float) $value, 2), null);
    }

    public static function unavailable(string $reason): self
    {
        return new self(null, $reason);
    }

    /**
     * Pourcentage arrondi à deux décimales, ou absence de valeur si le dénominateur est nul.
     */
    public static function percentage(int $numerator, int $denominator, string $emptyReason): self
    {
        if ($denominator <= 0) {
            return self::unavailable($emptyReason);
        }

        return self::of($numerator / $denominator * 100);
    }

    public function isAvailable(): bool
    {
        return $this->value !== null;
    }
}
