<?php

declare(strict_types=1);

namespace App\Domain\Community;

/**
 * Ce qu'un événement dévoile, et à qui (P8-04, FR-SOC-05 ; `simulateur.html` l.1164 : « Lieux sensibles : l'adresse
 * exacte n'est envoyée qu'aux participant·es confirmé·es »), sans base de données : l'appelant fournit les faits.
 *
 *  - la ville ou la zone (`place`) et le caractère « en ligne » : toute personne qui voit l'événement ;
 *  - l'adresse exacte et le lien de connexion : la personne qui a répondu « Je participe », l'organisateur·rice et la
 *    modération — jamais une personne seulement intéressée, jamais un·e visiteur·se, jamais une notification ;
 *  - un événement **public** (Q35 a) n'est ouvert aux visiteur·ses que publié, hors groupe privé ou inactif et hors page
 *    non approuvée ou inactive ; il ne dévoile alors que titre, dates, ville, catégorie, description et organisation ;
 *  - « Je participe » n'est possible que sur un événement publié qui n'est pas terminé (on peut toujours annuler).
 */
final class EventDisclosure
{
    private function __construct() {}

    public static function revealsExactPlace(bool $isGoing, bool $isOrganizer, bool $isModerator): bool
    {
        return $isGoing || $isOrganizer || $isModerator;
    }

    /**
     * @param  bool|null  $groupIsOpen  null sans groupe ; sinon groupe public et actif
     * @param  bool|null  $pageIsOpen  null sans page ; sinon page approuvée et active
     */
    public static function isOpenToPublic(bool $isPublic, bool $isPublished, ?bool $groupIsOpen, ?bool $pageIsOpen): bool
    {
        return $isPublic && $isPublished && $groupIsOpen !== false && $pageIsOpen !== false;
    }

    public static function acceptsResponses(bool $isPublished, bool $isUpcoming): bool
    {
        return $isPublished && $isUpcoming;
    }
}
