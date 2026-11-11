<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Dispatch;

/**
 * Nature d'une personne prévenue (P5-03, « Personnes prévenues ») : contact de confiance de l'auteur·e, point focal,
 * autre fiche de l'annuaire, équipe Xomiton (admin de garde), auteur·e elle-même (retours d'accusé et de statut).
 */
enum RecipientKind: string
{
    case Trusted = 'trusted';
    case Focal = 'focal';
    case Team = 'team';
    case Contact = 'contact';
    case Author = 'author';

    public function label(): string
    {
        return (string) __('enums/alerts.recipient_kind.'.$this->value);
    }

    /** Ordre d'affichage de la liste (la personne à appeler en premier en haut, le contact de confiance en dernier). */
    public function rank(): int
    {
        return match ($this) {
            self::Focal => 1,
            self::Contact => 2,
            self::Team => 3,
            self::Trusted => 4,
            self::Author => 5,
        };
    }
}
