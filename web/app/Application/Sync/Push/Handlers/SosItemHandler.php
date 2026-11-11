<?php

declare(strict_types=1);

namespace App\Application\Sync\Push\Handlers;

use App\Application\Alerts\TriggerSos;
use App\Application\Sync\Push\ItemHandler;
use App\Application\Sync\Push\ItemOutcome;
use App\Application\Sync\Push\PushContext;
use App\Application\Sync\Push\PushItem;

/**
 * `sos` : TriggerSos (P5-02) avec le compte et l'appareil, source `sync-offline` ; un champ refusé est retiré, jamais
 * le SOS (RG-01) ; notifications envoyées dans la requête (D7). Traité en premier dans le lot (SyncItemType::priority).
 * Jamais limité ; chaque SOS neuf passe par la surveillance des rafales (SosBurstWatch, P5-09, comme `POST sos`).
 */
final class SosItemHandler implements ItemHandler
{
    public function __construct(private readonly TriggerSos $trigger) {}

    public function handle(PushItem $item, PushContext $context): ItemOutcome
    {
        $result = ($this->trigger)($context->decoder->sos($item, $context->deviceId()));

        if (! $result->duplicate) {
            $context->limiter->sosReceived();

            return ItemOutcome::created($item, $result->alert);
        }

        return ItemOutcome::duplicate($item, $result->alert, hide: (int) $result->alert->user_id !== $context->userId());
    }
}
