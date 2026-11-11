<?php

declare(strict_types=1);

namespace App\Application\Community\Data;

/**
 * Filtres des suggestions « Qui suivre » (P8-06, who-to-follow.php l.97-104) : recherche sur le **pseudonyme** seulement
 * (jamais le nom réel, RG-02), zone (département, seulement pour les profils qui l'affichent) et rôle public
 * (App\Domain\Community\NetworkRules::ROLES : `member`, `peer`, `team`).
 */
final readonly class NetworkFilters
{
    public function __construct(
        public ?string $q = null,
        public ?int $zoneId = null,
        public ?string $role = null,
    ) {}

    public static function none(): self
    {
        return new self;
    }
}
