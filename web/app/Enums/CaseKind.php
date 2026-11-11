<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nature d'un dossier (DATABASE_DICTIONARY.md §6 `cases.kind`) : violence basée sur le genre ou violation
 * des droits humains. Détermine le préfixe du code de registre (RG-06 : `VBG-0000` / `VDH-0000`, compteurs séparés).
 */
enum CaseKind: string
{
    case Vbg = 'VBG';
    case Vdh = 'VDH';

    public function label(): string
    {
        return (string) __('enums/cases.case_kind.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Vbg => 'danger',
            self::Vdh => 'violet',
        };
    }

    /** Préfixe du code de registre, tiret compris (`VBG-`). */
    public function codePrefix(): string
    {
        return $this->value.'-';
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
