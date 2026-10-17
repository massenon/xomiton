<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use Database\Factories\ModerationReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Signalement de modération (DATABASE_DICTIONARY.md §7 `moderation_reports`). La cible est polymorphe
 * (`subject`) ; l'extrait est figé au signalement pour rester lisible si le contenu est supprimé.
 */
class ModerationReport extends Model
{
    /** @use HasFactory<ModerationReportFactory> */
    use HasFactory;

    /** Décisions possibles (`action`). */
    public const ACTION_HIDE = 'masque';

    public const ACTION_DELETE = 'supprime';

    public const ACTION_WARN = 'avertissement';

    public const ACTION_SUSPEND = 'suspension';

    public const ACTION_NONE = 'aucune';

    /** @var list<string> */
    public const ACTIONS = [self::ACTION_HIDE, self::ACTION_DELETE, self::ACTION_WARN, self::ACTION_SUSPEND, self::ACTION_NONE];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'subject_type',
        'subject_id',
        'excerpt',
        'reported_user_id',
        'reporter_user_id',
        'reporter_label',
        'reason',
        'details',
        'status',
        'action',
        'handled_by',
        'handled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ModerationKind::class,
            'subject_id' => 'integer',
            'status' => ModerationStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * Contenu ou compte signalé.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @return HasMany<UserWarning, $this>
     */
    public function warnings(): HasMany
    {
        return $this->hasMany(UserWarning::class);
    }

    /**
     * @param  Builder<ModerationReport>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ModerationStatus::Pending);
    }

    /**
     * Signalements déjà examinés (traités ou rejetés).
     *
     * @param  Builder<ModerationReport>  $query
     */
    public function scopeClosed(Builder $query): void
    {
        $query->where('status', '!=', ModerationStatus::Pending);
    }

    /**
     * @param  Builder<ModerationReport>  $query
     */
    public function scopeWithStatus(Builder $query, ModerationStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * @param  Builder<ModerationReport>  $query
     */
    public function scopeOfKind(Builder $query, ModerationKind $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * Signalements visant une même cible (compteur `posts.reports_count`, doublons).
     *
     * @param  Builder<ModerationReport>  $query
     */
    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey());
    }

    /**
     * @param  Builder<ModerationReport>  $query
     */
    public function scopeAgainstUser(Builder $query, User|int $user): void
    {
        $query->where('reported_user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /**
     * Libellé du ou de la signalant·e : pseudo du compte, sinon libellé libre, sinon « Anonyme ».
     */
    public function reporterLabel(): string
    {
        return $this->reporter?->pseudo ?? $this->reporter_label ?? 'Anonyme';
    }

    public function isPending(): bool
    {
        return $this->status === ModerationStatus::Pending;
    }
}
