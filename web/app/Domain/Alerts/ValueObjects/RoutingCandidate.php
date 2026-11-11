<?php

declare(strict_types=1);

namespace App\Domain\Alerts\ValueObjects;

use App\Enums\DirectoryRole;

/**
 * Contact de l'annuaire éligible au routage (actif, non supprimé) : identifiant, rôle, département et drapeau
 * « point focal national ». L'ordre des candidats (id croissant) est celui du prototype (ordre du fichier annuaire).
 */
final readonly class RoutingCandidate
{
    public function __construct(
        public int $id,
        public DirectoryRole $role,
        public ?int $zoneId = null,
        public bool $isNationalFocal = false,
    ) {}

    public function isFocal(): bool
    {
        return $this->role === DirectoryRole::PointFocal;
    }

    public function isInZone(?int $zoneId): bool
    {
        return $zoneId !== null && $this->zoneId === $zoneId;
    }
}
