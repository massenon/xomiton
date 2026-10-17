<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Famille d'outil du kit CCC numérisé (Communication pour le Changement de Comportement) :
 * modules du manuel, boîtes à images, bandes dessinées SSR et fiches de loi
 * (DATABASE_DICTIONARY.md §3 `ccc_tools.kind`).
 */
enum CccKind: string
{
    case Manual = 'manuel';
    case ImageBox = 'boite_images';
    case Comic = 'bd';
    case Law = 'loi';

    public function label(): string
    {
        return (string) __('enums/content.ccc_kind.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Manual => 'primary',
            self::ImageBox => 'info',
            self::Comic => 'violet',
            self::Law => 'secondary',
        };
    }

    /** Préfixe des clés publiques (`m1`, `bi1`, `bd1`, `loi1`). */
    public function keyPrefix(): string
    {
        return match ($this) {
            self::Manual => 'm',
            self::ImageBox => 'bi',
            self::Comic => 'bd',
            self::Law => 'loi',
        };
    }
}
