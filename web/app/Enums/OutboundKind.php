<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Contenu d'une publication sortante (DATABASE_DICTIONARY.md §8 `outbound_posts.kind`) : bande dessinée SSR
 * du kit CCC, message clé (modèle) ou texte personnalisé.
 */
enum OutboundKind: string
{
    case Bd = 'bd';
    case Template = 'template';
    case Custom = 'custom';

    public function label(): string
    {
        return (string) __('enums/gateways.outbound_kind.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Bd => 'violet',
            self::Template => 'info',
            self::Custom => 'secondary',
        };
    }

    /** La référence (`outbound_posts.reference`) est obligatoire pour une BD ou un modèle. */
    public function requiresReference(): bool
    {
        return $this !== self::Custom;
    }

    /**
     * Valeur du prototype (`gateway_posts.kind` : `bd`, `tpl`, `custom`).
     */
    public static function fromPrototype(string $kind): self
    {
        return match (trim($kind)) {
            'tpl' => self::Template,
            default => self::from(trim($kind)),
        };
    }
}
