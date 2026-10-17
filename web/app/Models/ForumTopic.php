<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\ForumTopicFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sujet de forum (DATABASE_DICTIONARY.md §4 `forum_topics`) : exposé par ULID, modéré comme une publication.
 */
class ForumTopic extends Model
{
    /** @use HasFactory<ForumTopicFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    public const ANONYMOUS_LABEL = 'Membre anonyme';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'forum_id',
        'user_id',
        'title',
        'body',
        'is_anonymous',
        'views_count',
        'replies_count',
        'last_reply_at',
        'is_pinned',
        'is_locked',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'views_count' => 'integer',
            'replies_count' => 'integer',
            'last_reply_at' => 'datetime',
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'status' => PostStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Forum, $this>
     */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ForumReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'topic_id')->orderBy('created_at');
    }

    /**
     * @param  Builder<ForumTopic>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published);
    }

    /**
     * @param  Builder<ForumTopic>  $query
     */
    public function scopePinned(Builder $query): void
    {
        $query->where('is_pinned', true);
    }

    /**
     * Ordre d'affichage d'un forum : épinglés d'abord, puis dernière activité.
     *
     * @param  Builder<ForumTopic>  $query
     */
    public function scopeByActivity(Builder $query): void
    {
        $query->orderByDesc('is_pinned')
            ->orderByRaw('COALESCE(last_reply_at, created_at) DESC');
    }

    /**
     * Pseudo de l'auteur·rice, ou libellé neutre si le sujet est anonyme ou le compte supprimé.
     */
    public function authorLabel(): string
    {
        if ($this->is_anonymous || $this->user === null) {
            return (string) __(self::ANONYMOUS_LABEL);
        }

        return $this->user->pseudo;
    }

    public function isLocked(): bool
    {
        return $this->is_locked;
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }
}
