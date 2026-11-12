<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cible de la queue d'une bulle (GRAMMAIRE_DES_BULLES.md §2, §6 ; `bd_bubbles.tail_target`) : la bouche du personnage,
 * l'écran d'un appareil (message reçu, voix d'un appareil) ou un bord de la case (voix hors de l'image).
 */
enum BubbleTailTarget: string
{
    case Character = 'personnage';
    case Screen = 'ecran';
    case EdgeLeft = 'bord-gauche';
    case EdgeRight = 'bord-droit';
    case EdgeTop = 'bord-haut';
    case EdgeBottom = 'bord-bas';

    public function isEdge(): bool
    {
        return str_starts_with($this->value, 'bord-');
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $target): string => $target->value, self::cases());
    }
}
