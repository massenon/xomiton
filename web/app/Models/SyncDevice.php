<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SyncDeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Appareil d'un·e pair·e synchronisé hors connexion (DATABASE_DICTIONARY.md §10 `sync_devices`).
 * Référencé par les alertes et les dossiers créés hors ligne.
 */
class SyncDevice extends Model
{
    /** @use HasFactory<SyncDeviceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_uuid',
        'label',
        'platform',
        'app_version',
        'last_seen_at',
        'last_sync_at',
        'pending_count',
        'conflicts_count',
        'is_revoked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'pending_count' => 'integer',
            'conflicts_count' => 'integer',
            'is_revoked' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'device_id');
    }

    /**
     * Dossiers collectés hors connexion sur cet appareil (lot 7 `cases.device_id`).
     *
     * @return HasMany<CaseRecord, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class, 'device_id');
    }

    /**
     * Accusés de réception des envois `sync/push` (lot 10 `sync_outbox_receipts`).
     *
     * @return HasMany<SyncOutboxReceipt, $this>
     */
    public function outboxReceipts(): HasMany
    {
        return $this->hasMany(SyncOutboxReceipt::class, 'device_id');
    }

    /**
     * @return HasMany<SyncConflict, $this>
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(SyncConflict::class, 'device_id');
    }

    /**
     * @return HasMany<SyncCursor, $this>
     */
    public function cursors(): HasMany
    {
        return $this->hasMany(SyncCursor::class, 'device_id');
    }

    /**
     * Appareils autorisés à synchroniser.
     *
     * @param  Builder<SyncDevice>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_revoked', false);
    }

    /**
     * @param  Builder<SyncDevice>  $query
     */
    public function scopeRevoked(Builder $query): void
    {
        $query->where('is_revoked', true);
    }

    /**
     * Appareils silencieux depuis plus de `xomiton.sync.device_stale_hours` heures.
     *
     * @param  Builder<SyncDevice>  $query
     */
    public function scopeStale(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', now()->subHours((int) config('xomiton.sync.device_stale_hours', 24)));
        });
    }

    /**
     * @param  Builder<SyncDevice>  $query
     */
    public function scopeWhereDeviceUuid(Builder $query, string $uuid): void
    {
        $query->where('device_uuid', $uuid);
    }

    public function isRevoked(): bool
    {
        return $this->is_revoked;
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts_count > 0;
    }
}
