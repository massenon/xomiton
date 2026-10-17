<?php

declare(strict_types=1);

namespace App\Enums;

enum MemberType: string
{
    case Member = 'membre';
    case Peer = 'pair';
    // Affichés à l'inscription seulement si le client les confirme (question Q du plan).
    case Ally = 'allie';
    case Resource = 'ressource';

    public function label(): string
    {
        return __('enums/identity.member_type.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Member => 'primary',
            self::Peer => 'violet',
            self::Ally => 'info',
            self::Resource => 'secondary',
        };
    }
}
