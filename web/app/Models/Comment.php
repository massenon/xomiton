<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commentaire d'une publication, éventuellement réponse à un autre commentaire.
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, SoftDeletes;

    public const BODY_MAX_LENGTH = 1000;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'body',
        'is_anonymous',
        'likes_count',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'likes_count' => 'integer',
            'status' => PostStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * @param  Builder<Comment>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published);
    }

    /**
     * Commentaires de premier niveau (sans parent).
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }
}
