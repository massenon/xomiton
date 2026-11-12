<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'en_attente';
    case Active = 'actif';
    case Suspended = 'suspendu';
    /** P18-06 (Q97) : bannissement, statut définitif distinct de la suspension, levé seulement par un·e superadmin. */
    case Banned = 'banni';

    public function label(): string
    {
        return __('enums/identity.user_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Banned => 'danger',
        };
    }

    /**
     * Statuts sans accès à la plateforme (connexion refusée), en valeurs brutes pour les requêtes.
     *
     * @return list<string>
     */
    public static function withoutAccess(): array
    {
        return [self::Suspended->value, self::Banned->value];
    }
}
