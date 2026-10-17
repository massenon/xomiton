<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sexe déclaré dans les dossiers (x_sexes() du prototype : Femme, Homme, Autre / non-binaire).
 */
enum Sex: string
{
    case Femme = 'femme';
    case Homme = 'homme';
    case Autre = 'autre';

    public function label(): string
    {
        return (string) __('enums/reference.sex.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Femme => 'primary',
            self::Homme => 'info',
            self::Autre => 'violet',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $case): string => $case->label(), self::cases()),
        );
    }
}
