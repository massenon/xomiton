<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConflictStatus;
use Database\Factories\SyncConflictFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * Conflit de synchronisation (DATABASE_DICTIONARY.md §10 `sync_conflicts`) : même champ modifié côté serveur et côté
 * appareil ; l'administration conserve la version serveur, la version appareil ou une fusion saisie à la main.
 */
class SyncConflict extends Model
{
    /** @use HasFactory<SyncConflictFactory> */
    use HasFactory;

    public const RESOLUTION_SERVER = 'serveur';

    public const RESOLUTION_DEVICE = 'appareil';

    public const RESOLUTION_MERGE = 'fusion';

    /** @var list<string> */
    public const RESOLUTIONS = [self::RESOLUTION_SERVER, self::RESOLUTION_DEVICE, self::RESOLUTION_MERGE];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'subject_type',
        'subject_id',
        'field',
        'server_value',
        'device_value',
        'status',
        'resolution',
        'resolved_value',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'status' => ConflictStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Appareil à l'origine du conflit (null s'il a été supprimé depuis).
     *
     * @return BelongsTo<SyncDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SyncDevice::class, 'device_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Enregistrement en conflit (dossier, alerte…).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<SyncConflict>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ConflictStatus::Open->value);
    }

    /**
     * @param  Builder<SyncConflict>  $query
     */
    public function scopeResolved(Builder $query): void
    {
        $query->where('status', ConflictStatus::Resolved->value);
    }

    /**
     * @param  Builder<SyncConflict>  $query
     */
    public function scopeForDevice(Builder $query, int $deviceId): void
    {
        $query->where('device_id', $deviceId);
    }

    /**
     * @param  Builder<SyncConflict>  $query
     */
    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey());
    }

    /**
     * @param  Builder<SyncConflict>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * Tranche le conflit : la valeur conservée découle de la résolution (fusion = valeur saisie obligatoire).
     */
    public function resolve(string $resolution, ?User $by = null, ?string $mergedValue = null): void
    {
        if (! in_array($resolution, self::RESOLUTIONS, true)) {
            throw new InvalidArgumentException('Résolution inconnue : '.$resolution);
        }

        if ($resolution === self::RESOLUTION_MERGE && $mergedValue === null) {
            throw new InvalidArgumentException('Une fusion exige la valeur conservée.');
        }

        $this->forceFill([
            'status' => ConflictStatus::Resolved,
            'resolution' => $resolution,
            'resolved_value' => match ($resolution) {
                self::RESOLUTION_SERVER => $this->server_value,
                self::RESOLUTION_DEVICE => $this->device_value,
                default => $mergedValue,
            },
            'resolved_by' => $by?->getKey(),
            'resolved_at' => now(),
        ])->save();
    }

    /**
     * Libellé de résolution du prototype (« version serveur conservée »).
     */
    public function resolutionLabel(): ?string
    {
        return $this->resolution === null ? null : 'version '.$this->resolution.' conservée';
    }
}
