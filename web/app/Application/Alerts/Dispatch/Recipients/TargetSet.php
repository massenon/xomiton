<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch\Recipients;

use App\Models\Alert;

/**
 * Liste ordonnée de destinataires sans doublon de personne (NOTIFICATIONS §4.2), auteur·e de l'alerte exclu·e : le
 * premier ajout d'une référence l'emporte (une fiche liée à un compte admin garde son libellé d'annuaire).
 *
 * @internal
 */
final class TargetSet
{
    /** @var array<string, RecipientTarget> */
    private array $targets = [];

    public function __construct(private readonly Alert $alert) {}

    public function add(RecipientTarget $target): void
    {
        if ($target->user !== null && $this->alert->user_id !== null && (int) $target->user->getKey() === (int) $this->alert->user_id) {
            return;
        }

        $this->targets[$target->reference] ??= $target;
    }

    /**
     * @return list<RecipientTarget>
     */
    public function all(): array
    {
        return array_values($this->targets);
    }
}
