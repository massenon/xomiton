<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

use App\Domain\Sync\SyncItemType;

/**
 * Quotas par élément de `sync/push` (P9-06) : un lot ne contourne jamais les limiteurs des routes dédiées (message →
 * `messages_write`, signalement → `report`, même compteur que `POST conversations/{ulid}/messages` et
 * `POST signalements`). Le SOS n'est jamais limité (RG-01, P2-08) : chaque SOS neuf passe par la surveillance des
 * rafales (SosBurstWatch, P5-09). Implémenté côté HTTP par App\Http\Requests\Api\Sync\SyncItemLimiter.
 */
interface ItemLimiter
{
    /** Compte l'élément ; false quand le quota du type est atteint (élément 429, nouvel essai dans un prochain lot). */
    public function attempt(SyncItemType $type): bool;

    /** SOS neuf reçu par la synchronisation. */
    public function sosReceived(): void;
}
