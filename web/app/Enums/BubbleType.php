<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Les 9 types de bulles de la grammaire (design-system/bd/conversations/GRAMMAIRE_DES_BULLES.md §3 ; `bd_bubbles.type`).
 * La forme porte un sens, jamais seule : le type est aussi annoncé aux lecteurs d'écran (« Fifamè pense : … »).
 */
enum BubbleType: string
{
    case Speech = 'parole';
    case Thought = 'pensee';
    case Shout = 'cri';
    case Shaky = 'tremblee';
    case Whisper = 'chuchote';
    case Joy = 'joie';
    case Message = 'message';
    case DeviceVoice = 'voix_appareil';
    case Caption = 'cartouche';

    public function label(): string
    {
        return (string) __('conversations_bd.types.'.$this->value);
    }

    /** Le cartouche (récitatif) n'a ni queue ni personnage et ne compte pas dans la limite de 4 bulles par case. */
    public function isCaption(): bool
    {
        return $this === self::Caption;
    }

    /** Bulles masquées en mode séance jusqu'à « Révéler les pensées ». */
    public function isThought(): bool
    {
        return $this === self::Thought;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
