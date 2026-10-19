<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Enums\DirectoryRole;

/**
 * Filtres de l'annuaire membre (prototype annuaire.php l.30-37) : recherche (nom, organisation, rôle, commune), rôle,
 * département et commune (la commune n'a de sens qu'avec son département).
 */
final readonly class DirectoryFilters
{
    public function __construct(
        public ?string $q = null,
        public ?DirectoryRole $role = null,
        public ?int $zoneId = null,
        public ?int $communeId = null,
    ) {}

    /** Mêmes filtres sans département ni commune : décompte de la carte par département. */
    public function withoutPlace(): self
    {
        return new self($this->q, $this->role);
    }

    /**
     * Paramètres d'adresse (aller-retour des filtres, `?q=&role=&zone=&commune=`).
     *
     * @return array{q: string|null, role: string|null, zone: int|null, commune: int|null}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->q,
            'role' => $this->role?->value,
            'zone' => $this->zoneId,
            'commune' => $this->communeId,
        ];
    }
}
