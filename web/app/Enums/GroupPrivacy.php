<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Confidentialité d'un groupe : public (contenu lisible par la communauté) ou privé (membres validés).
 */
enum GroupPrivacy: string
{
    case Public = 'public';
    case Private = 'prive';

    public function label(): string
    {
        return (string) __('enums/feed.group_privacy.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Private => 'secondary',
        };
    }

    /**
     * Depuis le libellé du prototype (`Public` / `Privé`).
     */
    public static function fromLabel(string $label): self
    {
        return mb_strtolower(trim($label)) === 'privé' || mb_strtolower(trim($label)) === 'prive'
            ? self::Private
            : self::Public;
    }
}
