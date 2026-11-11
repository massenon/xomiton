<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SyncCursorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Curseur de synchronisation descendante (DATABASE_DICTIONARY.md §10 `sync_cursors`) : dernier identifiant tiré
 * par un appareil pour une collection, afin de ne renvoyer que le delta au prochain `pull`.
 */
class SyncCursor extends Model
{
    /** @use HasFactory<SyncCursorFactory> */
    use HasFactory;

    /** @var list<string> collections de `GET sync/pull` (SOFTWARE_ARCHITECTURE.md §5, App\Application\Sync\Pull\PullChanges::COLLECTIONS) */
    public const COLLECTIONS = ['emergency_contacts', 'directory_contacts', 'thematiques', 'validation_rules', 'cases', 'case_followups', 'alerts'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'collection',
        'cursor',
        'pulled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cursor' => 'integer',
            'pulled_at' => 'datetime',
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
     * @param  Builder<SyncCursor>  $query
     */
    public function scopeForDevice(Builder $query, int $deviceId): void
    {
        $query->where('device_id', $deviceId);
    }

    /**
     * @param  Builder<SyncCursor>  $query
     */
    public function scopeForCollection(Builder $query, string $collection): void
    {
        $query->where('collection', $collection);
    }

    /**
     * Curseur d'un appareil pour une collection, créé à zéro s'il n'existe pas encore.
     */
    public static function obtain(SyncDevice $device, string $collection): self
    {
        return static::query()->firstOrCreate(
            ['device_id' => $device->getKey(), 'collection' => $collection],
            ['cursor' => 0],
        );
    }

    /**
     * Avance le curseur (jamais en arrière) et date le tirage.
     */
    public function advanceTo(int $cursor): void
    {
        $this->forceFill([
            'cursor' => max($this->cursor, $cursor),
            'pulled_at' => now(),
        ])->save();
    }

    public function isFresh(): bool
    {
        return $this->cursor === 0 && $this->pulled_at === null;
    }
}
