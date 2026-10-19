<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

/**
 * Modification hors connexion d'un dossier déjà synchronisé (`collecte`, `op: update`, P9-06) : dossier visé par son
 * identifiant ou son code, nouvelles valeurs (`changes`, validées) et valeurs vues par l'appareil avant la
 * modification (`base`, une par champ modifié) : la fusion par champ (App\Domain\Sync\FieldMerge) en déduit les
 * modifications concurrentes.
 */
final readonly class CaseChanges
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $base
     */
    public function __construct(
        public ?int $caseId,
        public ?string $caseCode,
        public array $changes,
        public array $base,
    ) {}
}
