<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

use App\Models\SyncOutboxReceipt;

/**
 * Élément d'un lot `POST sync/push` (P9-06) : `uuid` = identifiant de l'entrée de la boîte d'envoi de l'appareil, gardé
 * à chaque rejeu (clé d'idempotence de l'alerte, `client_uuid` du message ou du dossier, ADR-32) ; `op` = `create`
 * (défaut) ou `update` (modification d'un dossier déjà synchronisé, fusion par champ) ; `payload` = mêmes champs que
 * la route dédiée (`POST signalements`, `POST sos`, `POST conversations/{ulid}/messages`, Q11 a).
 */
final readonly class PushItem
{
    public const OP_CREATE = 'create';

    public const OP_UPDATE = 'update';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $index,
        public string $uuid,
        public string $type,
        public string $op,
        public array $payload,
    ) {}

    public function isUpdate(): bool
    {
        return $this->op === self::OP_UPDATE;
    }

    /** Empreinte stable (clés triées à tous les niveaux) gardée dans le reçu : même uuid + autre contenu = refus. */
    public function hash(): string
    {
        return SyncOutboxReceipt::hashPayload(self::canonical(['type' => $this->type, 'op' => $this->op, 'payload' => $this->payload]));
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(static fn (mixed $item): mixed => is_array($item) ? self::canonical($item) : $item, $value);
    }
}
