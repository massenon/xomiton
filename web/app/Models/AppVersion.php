<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AppVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Version de la plateforme (DATABASE_DICTIONARY.md §10 `app_versions`) : déployée ou planifiée. La version courante
 * est la dernière déployée (`Setting` `platform_version` en est le reflet pour l'affichage).
 */
class AppVersion extends Model
{
    /** @use HasFactory<AppVersionFactory> */
    use HasFactory;

    public const KIND_FIX = 'correctif';

    public const KIND_FEATURE = 'fonctionnalite';

    public const KIND_SECURITY = 'securite';

    /** @var list<string> */
    public const KINDS = [self::KIND_FIX, self::KIND_FEATURE, self::KIND_SECURITY];

    public const STATUS_PLANNED = 'planifiee';

    public const STATUS_DEPLOYED = 'deployee';

    public const STATUS_CANCELLED = 'annulee';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_PLANNED, self::STATUS_DEPLOYED, self::STATUS_CANCELLED];

    /** Format attendu d'un numéro de version (écran « Mises à jour » : `1.5.0`). */
    public const VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'version',
        'kind',
        'notes',
        'status',
        'scheduled_for',
        'deployed_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'deployed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<AppVersion>  $query
     */
    public function scopeDeployed(Builder $query): void
    {
        $query->where('status', self::STATUS_DEPLOYED);
    }

    /**
     * @param  Builder<AppVersion>  $query
     */
    public function scopePlanned(Builder $query): void
    {
        $query->where('status', self::STATUS_PLANNED);
    }

    /**
     * @param  Builder<AppVersion>  $query
     */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * Dernière version déployée (la plus récemment mise en production).
     */
    public static function current(): ?self
    {
        return static::query()->deployed()->orderByDesc('deployed_at')->orderByDesc('id')->first();
    }

    public function isDeployed(): bool
    {
        return $this->status === self::STATUS_DEPLOYED;
    }

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    /**
     * Cette version est-elle strictement supérieure à `$other` (`1.5.0` > `1.4.2`) ?
     */
    public function isNewerThan(string $other): bool
    {
        return version_compare($this->version, $other, '>');
    }

    /**
     * Marque la version comme déployée maintenant (ou à la date donnée).
     */
    public function markDeployed(?Carbon $at = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_DEPLOYED,
            'deployed_at' => $at ?? now(),
        ])->save();
    }

    /**
     * Badge Bootstrap par type, comme l'écran du prototype (`$tcls`).
     */
    public function kindBadge(): string
    {
        return match ($this->kind) {
            self::KIND_FEATURE => 'primary',
            self::KIND_SECURITY => 'danger',
            default => 'success',
        };
    }

    /**
     * Type du prototype (`updates.type` : « Correctif », « Fonctionnalité », « Sécurité », « Optimisation »).
     */
    public static function kindFromPrototype(string $type): string
    {
        return match (mb_strtolower(trim($type))) {
            'fonctionnalité', 'fonctionnalite' => self::KIND_FEATURE,
            'sécurité', 'securite' => self::KIND_SECURITY,
            default => self::KIND_FIX,
        };
    }

    /**
     * Statut du prototype (`updates.status` : « déployée », « planifiée »).
     */
    public static function statusFromPrototype(string $status): string
    {
        return match (mb_strtolower(trim($status))) {
            'déployée', 'deployee' => self::STATUS_DEPLOYED,
            'annulée', 'annulee' => self::STATUS_CANCELLED,
            default => self::STATUS_PLANNED,
        };
    }
}
