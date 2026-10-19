<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SyncOutboxReceiptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Accusé de réception d'un élément poussé hors connexion (DATABASE_DICTIONARY.md §10 `sync_outbox_receipts`) :
 * la clé d'idempotence par appareil garantit qu'un rejeu renvoie le même résultat sans recréer l'objet.
 */
class SyncOutboxReceipt extends Model
{
    /** @use HasFactory<SyncOutboxReceiptFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const KIND_CASE = 'case';

    public const KIND_FOLLOWUP = 'followup';

    public const KIND_ALERT = 'alert';

    public const KIND_SOS = 'sos';

    public const KIND_MESSAGE = 'message';

    /** @var list<string> */
    public const KINDS = [self::KIND_CASE, self::KIND_FOLLOWUP, self::KIND_ALERT, self::KIND_SOS, self::KIND_MESSAGE];

    public const RESULT_CREATED = 'created';

    /** Modification d'un enregistrement déjà synchronisé appliquée sans conflit (P9-06, `op: update`). */
    public const RESULT_UPDATED = 'updated';

    public const RESULT_DUPLICATE = 'duplicate';

    public const RESULT_CONFLICT = 'conflict';

    public const RESULT_REJECTED = 'rejected';

    /** @var list<string> */
    public const RESULTS = [self::RESULT_CREATED, self::RESULT_UPDATED, self::RESULT_DUPLICATE, self::RESULT_CONFLICT, self::RESULT_REJECTED];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'idempotency_key',
        'kind',
        'subject_type',
        'subject_id',
        'result',
        'payload_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SyncDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SyncDevice::class, 'device_id');
    }

    /**
     * Objet créé côté serveur (dossier, alerte…), s'il existe encore.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<SyncOutboxReceipt>  $query
     */
    public function scopeForDevice(Builder $query, int $deviceId): void
    {
        $query->where('device_id', $deviceId);
    }

    /**
     * @param  Builder<SyncOutboxReceipt>  $query
     */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * @param  Builder<SyncOutboxReceipt>  $query
     */
    public function scopeWithResult(Builder $query, string $result): void
    {
        $query->where('result', $result);
    }

    /**
     * Reçus ayant abouti à une création.
     *
     * @param  Builder<SyncOutboxReceipt>  $query
     */
    public function scopeAccepted(Builder $query): void
    {
        $query->where('result', self::RESULT_CREATED);
    }

    /**
     * Reçus plus anciens que `$days` jours : la fenêtre de rejeu est passée, `sync:prune` les supprime.
     *
     * @param  Builder<SyncOutboxReceipt>  $query
     */
    public function scopeOlderThan(Builder $query, int $days): void
    {
        $query->where('created_at', '<', now()->subDays($days));
    }

    /**
     * Reçu déjà émis pour cette clé d'idempotence sur cet appareil (rejeu), s'il existe.
     */
    public static function findForReplay(int $deviceId, string $idempotencyKey): ?self
    {
        return static::query()->forDevice($deviceId)->where('idempotency_key', $idempotencyKey)->first();
    }

    /**
     * Le contenu rejoué est-il identique à celui reçu la première fois ?
     */
    public function matchesPayload(array|string $payload): bool
    {
        return hash_equals($this->payload_hash, static::hashPayload($payload));
    }

    public function wasCreated(): bool
    {
        return $this->result === self::RESULT_CREATED;
    }

    /**
     * Empreinte SHA-256 stable d'un contenu (tableau trié par clé, ou chaîne brute).
     *
     * @param  array<mixed>|string  $payload
     */
    public static function hashPayload(array|string $payload): string
    {
        if (is_array($payload)) {
            ksort($payload);
            $payload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return hash('sha256', $payload);
    }
}
