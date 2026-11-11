<?php

declare(strict_types=1);

namespace App\Domain\Sync;

use App\Models\SyncOutboxReceipt;

/**
 * Types d'éléments acceptés par `POST sync/push` (P9-06, prototype api.php `sync` : signalement, sos, collecte ;
 * messages ajoutés par P9-07). Le type fixe la nature du reçu (`sync_outbox_receipts.kind`) et l'ordre de traitement
 * dans un lot : SOS d'abord (SOFTWARE_ARCHITECTURE.md §5 : le SOS n'attend jamais), puis signalements, collecte,
 * messages.
 */
enum SyncItemType: string
{
    case Sos = 'sos';
    case Report = 'signalement';
    case Case = 'collecte';
    case Message = 'message';

    public function receiptKind(): string
    {
        return match ($this) {
            self::Sos => SyncOutboxReceipt::KIND_SOS,
            self::Report => SyncOutboxReceipt::KIND_ALERT,
            self::Case => SyncOutboxReceipt::KIND_CASE,
            self::Message => SyncOutboxReceipt::KIND_MESSAGE,
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::Sos => 0,
            self::Report => 10,
            self::Case => 20,
            self::Message => 30,
        };
    }
}
