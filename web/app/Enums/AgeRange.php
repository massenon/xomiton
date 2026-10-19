<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tranches d'âge utilisées par les profils et les dossiers (x_age_ranges() du prototype).
 */
enum AgeRange: string
{
    case From10To14 = '10-14';
    case From15To17 = '15-17';
    case From18To24 = '18-24';
    case From25To34 = '25-34';
    case From35To49 = '35-49';
    case FiftyPlus = '50+';

    public function label(): string
    {
        return (string) __('enums/reference.age_range.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::From10To14 => 'danger',
            self::From15To17 => 'warning',
            self::From18To24 => 'info',
            self::From25To34, self::From35To49 => 'primary',
            self::FiftyPlus => 'secondary',
        };
    }

    /** Âge le plus bas de la tranche (plausibilité RG-08, Q25 a). */
    public function lowerBound(): int
    {
        return (int) explode('-', rtrim($this->value, '+'))[0];
    }

    /** Âge le plus haut de la tranche ; null pour la tranche ouverte « 50 ans et plus ». */
    public function upperBound(): ?int
    {
        $parts = explode('-', $this->value);

        return count($parts) === 2 ? (int) $parts[1] : null;
    }

    /** Vrai pour les tranches mineures (signalement à traiter avec une vigilance renforcée). */
    public function isMinor(): bool
    {
        return in_array($this, [self::From10To14, self::From15To17], true);
    }

    /** @return array<string, string> valeur => libellé, pour les listes déroulantes */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $case): string => $case->label(), self::cases()),
        );
    }
}
