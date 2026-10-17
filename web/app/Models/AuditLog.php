<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Entrée du journal d'audit (DATABASE_DICTIONARY.md §10 `audit_logs`) : écrite une fois, jamais modifiée.
 * Toute tentative de mise à jour lève une exception ; seule la purge planifiée supprime (`scopeOlderThan`).
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const ACTOR_ANONYMOUS = 'anonyme';

    public const ACTOR_SYSTEM = 'système';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'actor',
        'action',
        'subject_type',
        'subject_id',
        'detail',
        'ip',
        'user_agent',
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

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Le journal d’audit est immuable : une entrée ne se modifie pas.');
        });
    }

    /**
     * Compte à l'origine de l'action (null : anonyme, système ou compte supprimé ; voir `actor`).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Objet concerné quand il est encore présent en base.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey());
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function scopeOfAction(Builder $query, string $action): void
    {
        $query->where('action', $action);
    }

    /**
     * Filtre par période (bornes incluses, journée entière), comme l'écran Journal du prototype.
     *
     * @param  Builder<AuditLog>  $query
     */
    public function scopeBetween(Builder $query, ?Carbon $from, ?Carbon $until): void
    {
        if ($from !== null) {
            $query->where('created_at', '>=', $from->copy()->startOfDay());
        }

        if ($until !== null) {
            $query->where('created_at', '<=', $until->copy()->endOfDay());
        }
    }

    /**
     * Entrées à purger : plus anciennes que `$days` jours (`xomiton.retention.audit_log_days` par défaut).
     *
     * @param  Builder<AuditLog>  $query
     */
    public function scopeOlderThan(Builder $query, ?int $days = null): void
    {
        $days ??= (int) config('xomiton.retention.audit_log_days', 90);

        $query->where('created_at', '<', now()->subDays($days));
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Libellé affiché pour l'auteur·rice : e-mail conservé, ou « anonyme ».
     */
    public function actorLabel(): string
    {
        return $this->actor ?? $this->user?->email ?? self::ACTOR_ANONYMOUS;
    }
}
