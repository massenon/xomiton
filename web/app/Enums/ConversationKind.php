<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nature d'une conversation : privée (deux membres) ou de groupe (salon, groupe de la communauté).
 */
enum ConversationKind: string
{
    case Private = 'private';
    case Group = 'group';

    public function label(): string
    {
        return (string) __('enums/community.conversation_kind.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Private => 'secondary',
            self::Group => 'primary',
        };
    }

    public function isGroup(): bool
    {
        return $this === self::Group;
    }
}
