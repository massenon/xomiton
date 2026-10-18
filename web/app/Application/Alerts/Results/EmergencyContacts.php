<?php

declare(strict_types=1);

namespace App\Application\Alerts\Results;

use App\Models\DirectoryContact;
use App\Models\Zone;
use Illuminate\Support\Collection;

/**
 * Contacts d'urgence (P5-06 `GET urgences`, P4-06 `/urgences`) : numéros nationaux et fiches d'urgence publiques de
 * l'annuaire, celles du département demandé d'abord ; `truncated` : plus de fiches que le plafond de la requête.
 */
final readonly class EmergencyContacts
{
    /**
     * @param  list<array{label: string, number: string}>  $services
     * @param  Collection<int, DirectoryContact>  $contacts
     */
    public function __construct(
        public array $services,
        public Collection $contacts,
        public ?Zone $zone = null,
        public bool $truncated = false,
    ) {}
}
