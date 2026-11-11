<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use Database\Factories\ForumReplyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Réponse à un sujet de forum (DATABASE_DICTIONARY.md §4 `forum_replies`).
 */
class ForumReply extends Model
{
    /** @use HasFactory<ForumReplyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'topic_id',
        'user_id',
        'body',
        'is_anonymous',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'status' => PostStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ForumTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<ForumReply>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published);
    }

    /**
     * Pseudo de l'auteur·rice, ou libellé neutre si la réponse est anonyme ou le compte supprimé.
     */
    public function authorLabel(): string
    {
        if ($this->is_anonymous || $this->user === null) {
            return (string) __(ForumTopic::ANONYMOUS_LABEL);
        }

        return $this->user->pseudo;
    }
}
