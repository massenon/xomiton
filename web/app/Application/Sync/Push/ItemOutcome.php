<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

use App\Models\Alert;
use App\Models\CaseRecord;
use App\Models\SyncOutboxReceipt;
use Illuminate\Database\Eloquent\Model;

/**
 * Réponse à un élément de `sync/push` (P9-06, ADR-32) : `{uuid, type, result, status, server_id, reference, code,
 * message?, errors?, conflicts?, applied?, kept?, case?, warnings?}`. `result` = `created` | `updated` | `duplicate` |
 * `conflict` | `rejected` (valeurs de `sync_outbox_receipts.result`) ; `status` = code HTTP de l'élément (201, 200,
 * 409, 4xx, 5xx) : l'appareil retire l'entrée sauf sur 429 et 5xx (nouvel essai dans un nouveau lot). `server_id` =
 * identifiant public (ULID d'une alerte, identifiant d'un message ou d'un dossier) ; `reference` = code de suivi d'une
 * alerte, code `VBG-0000` d'un dossier ; `code` = code stable d'un refus ou d'un conflit (null sinon) ; `case` = état
 * courant du dossier après une modification (contenu de la collection `cases` de `sync/pull`) ; `warnings` =
 * avertissements non bloquants d'un dossier (CaseQuality).
 */
final readonly class ItemOutcome
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  list<array{field: string, conflict_id: int}>  $conflicts
     * @param  list<string>  $applied
     * @param  list<string>  $kept
     * @param  array<string, mixed>|null  $record
     * @param  list<array{code: string, message: string, codes: list<string>}>  $warnings
     */
    public function __construct(
        public string $uuid,
        public string $type,
        public string $result,
        public int $status,
        public ?Model $subject = null,
        public ?string $code = null,
        public ?string $message = null,
        public array $errors = [],
        public array $conflicts = [],
        public array $applied = [],
        public array $kept = [],
        public bool $storable = true,
        public bool $hideSubject = false,
        public ?array $record = null,
        public array $warnings = [],
    ) {}

    /**
     * @param  list<array{code: string, message: string, codes: list<string>}>  $warnings
     */
    public static function created(PushItem $item, Model $subject, array $warnings = []): self
    {
        return new self($item->uuid, $item->type, SyncOutboxReceipt::RESULT_CREATED, 201, $subject, warnings: $warnings);
    }

    /**
     * Déjà reçu (même uuid) ; `$hide` : objet d'un autre compte (uuid repris), ni identifiant ni référence rendus.
     *
     * @param  array<string, mixed>|null  $record
     */
    public static function duplicate(PushItem $item, ?Model $subject, bool $hide = false, ?array $record = null): self
    {
        return new self($item->uuid, $item->type, SyncOutboxReceipt::RESULT_DUPLICATE, 200, $subject, hideSubject: $hide, record: $record);
    }

    /**
     * @param  list<string>  $applied
     * @param  list<string>  $kept
     * @param  array<string, mixed>|null  $record
     * @param  list<array{code: string, message: string, codes: list<string>}>  $warnings
     */
    public static function updated(PushItem $item, Model $subject, array $applied, array $kept, ?array $record = null, array $warnings = []): self
    {
        return new self($item->uuid, $item->type, SyncOutboxReceipt::RESULT_UPDATED, 200, $subject,
            applied: $applied, kept: $kept, record: $record, warnings: $warnings);
    }

    /**
     * Conflit (à la première réception, ou rendu au rejeu de l'élément depuis `sync_conflicts`, ADR-32 : réponse
     * d'origine) ; `$hide` : dossier plus visible du compte, ni identifiant, ni référence, ni état.
     *
     * @param  list<array{field: string, conflict_id: int}>  $conflicts
     * @param  list<string>  $applied
     * @param  list<string>  $kept
     * @param  array<string, mixed>|null  $record
     * @param  list<array{code: string, message: string, codes: list<string>}>  $warnings
     */
    public static function conflict(PushItem $item, Model $subject, array $conflicts, array $applied, array $kept, ?array $record = null, array $warnings = [], bool $hide = false): self
    {
        return new self($item->uuid, $item->type, SyncOutboxReceipt::RESULT_CONFLICT, 409, $subject, 'conflict',
            (string) __('sync.items.conflict'), conflicts: $conflicts, applied: $applied, kept: $kept, hideSubject: $hide,
            record: $record, warnings: $warnings);
    }

    public static function rejected(PushItem $item, ItemRejected $rejection): self
    {
        return new self($item->uuid, $item->type, SyncOutboxReceipt::RESULT_REJECTED, $rejection->status, null,
            $rejection->errorCode, $rejection->getMessage(), $rejection->errors, storable: $rejection->storable && $rejection->status < 500);
    }

    /**
     * Élément non traité (erreur imprévue, budget de temps du lot épuisé, maintenance) : rien n'est gardé, l'appareil
     * renverra l'élément dans un prochain lot.
     */
    public static function retry(PushItem $item, ?string $message = null): self
    {
        return new self($item->uuid, $item->type, SyncOutboxReceipt::RESULT_REJECTED, 503, null, 'retry_later',
            $message ?? (string) __('sync.items.retry'), storable: false);
    }

    /** Quota du type atteint (`messages_write`, `report`) : rien n'est gardé, nouvel essai dans un prochain lot. */
    public static function throttled(PushItem $item): self
    {
        return new self($item->uuid, $item->type, SyncOutboxReceipt::RESULT_REJECTED, 429, null, 'too_many_requests',
            (string) __('sync.items.too_many'), storable: false);
    }

    public function serverId(): string|int|null
    {
        if ($this->hideSubject) {
            return null;
        }

        return match (true) {
            $this->subject instanceof Alert => (string) $this->subject->ulid,
            $this->subject instanceof Model => (int) $this->subject->getKey(),
            default => null,
        };
    }

    public function reference(): ?string
    {
        if ($this->hideSubject) {
            return null;
        }

        return match (true) {
            $this->subject instanceof Alert => (string) $this->subject->tracking_code,
            $this->subject instanceof CaseRecord => (string) $this->subject->code,
            default => null,
        };
    }

    /** Sujet gardé dans le reçu (jamais celui d'un autre compte). */
    public function receiptSubject(): ?Model
    {
        return $this->hideSubject ? null : $this->subject;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $row = [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'result' => $this->result,
            'status' => $this->status,
            'server_id' => $this->serverId(),
            'reference' => $this->reference(),
            'code' => $this->code,
        ];

        if ($this->message !== null) {
            $row['message'] = $this->message;
        }

        if ($this->errors !== []) {
            $row['errors'] = $this->errors;
        }

        if ($this->conflicts !== []) {
            $row['conflicts'] = $this->conflicts;
        }

        if ($this->result === SyncOutboxReceipt::RESULT_UPDATED || $this->result === SyncOutboxReceipt::RESULT_CONFLICT) {
            $row['applied'] = $this->applied;
            $row['kept'] = $this->kept;
        }

        if ($this->record !== null && ! $this->hideSubject) {
            $row['case'] = $this->record;
        }

        if ($this->warnings !== []) {
            $row['warnings'] = $this->warnings;
        }

        return $row;
    }
}
