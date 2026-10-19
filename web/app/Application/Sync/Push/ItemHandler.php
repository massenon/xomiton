<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Traitement d'un type d'élément de `sync/push` (P9-06) par le cas d'usage de la route dédiée.
 */
interface ItemHandler
{
    /**
     * @throws ItemRejected
     * @throws DomainException refus métier du cas d'usage, rendu tel quel dans l'élément
     */
    public function handle(PushItem $item, PushContext $context): ItemOutcome;
}
