<?php

declare(strict_types=1);

namespace App\Application\Sync\Push\Handlers;

use App\Application\Alerts\CreateReport;
use App\Application\Sync\Push\ItemHandler;
use App\Application\Sync\Push\ItemOutcome;
use App\Application\Sync\Push\ItemRejected;
use App\Application\Sync\Push\PushContext;
use App\Application\Sync\Push\PushItem;
use App\Domain\Alerts\Exceptions\IdempotencyKeyConflict;

/**
 * `signalement` (signalement ou besoin) : CreateReport (P5-02), source `sync-offline`, appareil rattaché, clé
 * d'idempotence = uuid de l'élément (ADR-32). Jamais anonyme : un signalement anonyme passe par POST signalements
 * (ADR-26, RG-01 : le reçu relierait l'alerte à l'appareil, donc au compte). Uuid déjà pris par une alerte d'un autre
 * compte : `duplicate` sans identifiant ni code de suivi.
 */
final class ReportItemHandler implements ItemHandler
{
    public function __construct(private readonly CreateReport $create) {}

    public function handle(PushItem $item, PushContext $context): ItemOutcome
    {
        $data = $context->decoder->report($item, $context->deviceId());

        try {
            $result = ($this->create)($data);
        } catch (IdempotencyKeyConflict $exception) {
            throw ItemRejected::fromDomain($exception);
        }

        if (! $result->duplicate) {
            return ItemOutcome::created($item, $result->alert);
        }

        return ItemOutcome::duplicate($item, $result->alert, hide: (int) $result->alert->user_id !== $context->userId());
    }
}
