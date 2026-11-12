<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\Concerns\HasUlid;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Publication du fil (DATABASE_DICTIONARY.md §4 `posts`).
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    /**
     * Longueur maximale du corps (DATABASE_ENGINEERING.md §3).
     */
    public const BODY_MAX_LENGTH = 3000;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'user_id',
        'group_id',
        'page_id',
        'is_anonymous',
        'body',
        'tag',
        'visibility',
        'likes_count',
        'comments_count',
        'reports_count',
        'status',
        'hidden_reason',
        'published_at',
        'edited_at',
        'reviewed_by',
        'reviewed_at',
        'review_reason',
        'review_note',
    ];

    /**
     * Le motif de masquage est réservé à la modération.
     *
     * @var list<string>
     */
    protected $hidden = [
        'hidden_reason',
        'reports_count',
        'reviewed_by',
        'review_reason',
        'review_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'tag' => PostTag::class,
            'visibility' => PostVisibility::class,
            'status' => PostStatus::class,
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'reports_count' => 'integer',
            'published_at' => 'datetime',
            'edited_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Une publication est datée dès sa création (pas de brouillon côté serveur). Une vidéo en relecture porte la date
        // d'envoi en attendant : `published_at` est remplacé par la date d'approbation (ReviewVideo), l'API l'expose nul.
        static::creating(function (Post $post): void {
            $post->published_at ??= now();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return HasMany<PostMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('position');
    }

    /**
     * @return HasMany<PostLike, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    /**
     * @return HasMany<PostSave, $this>
     */
    public function saves(): HasMany
    {
        return $this->hasMany(PostSave::class);
    }

    /**
     * Masquages par des membres (« Masquer » : retirée de leur fil seulement, P7-02).
     *
     * @return HasMany<PostHide, $this>
     */
    public function hides(): HasMany
    {
        return $this->hasMany(PostHide::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return HasOne<Poll, $this>
     */
    public function poll(): HasOne
    {
        return $this->hasOne(Poll::class);
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published);
    }

    /**
     * Vidéos en attente de relecture, les plus anciennes d'abord (file de la modération, Q96).
     *
     * @param  Builder<Post>  $query
     */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->where('status', PostStatus::Pending)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeTagged(Builder $query, PostTag $tag): void
    {
        $query->where('tag', $tag);
    }

    /**
     * Publications lisibles par un·e membre (ou un·e visiteur·se si null) : publiées et publiques,
     * ou bien les siennes, ou réservées aux abonné·es d'un·e auteur·rice que l'on suit.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): void
    {
        $query->published()->where(function (Builder $q) use ($viewer): void {
            $q->where('visibility', PostVisibility::Public);

            if ($viewer === null) {
                return;
            }

            $q->orWhere('user_id', $viewer->id)
                ->orWhere(function (Builder $r) use ($viewer): void {
                    // Anonyme + abonné·es : traitée comme « moi » (Q72) ; sinon, la voir révélerait qui l'a écrite.
                    $r->where('visibility', PostVisibility::Followers)
                        ->where('is_anonymous', false)
                        ->whereIn('user_id', Follow::query()->select('followed_id')->where('follower_id', $viewer->id));
                });
        });
    }

    /**
     * Ordre du fil : plus récent d'abord.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isPoll(): bool
    {
        return $this->tag === PostTag::Poll;
    }

    public function isPublished(): bool
    {
        return $this->currentStatus() === PostStatus::Published;
    }

    /**
     * Statut lu dans l'attribut brut : stable pour l'analyse statique (cast d'énumération) et pour une valeur modifiée
     * en mémoire avant l'enregistrement.
     */
    public function currentStatus(): ?PostStatus
    {
        return PostStatus::tryFrom((string) ($this->attributes['status'] ?? ''));
    }

    /** Vidéo en attente de relecture (Q96). */
    public function isPendingReview(): bool
    {
        return $this->currentStatus() === PostStatus::Pending;
    }

    /** Vidéo en attente de relecture ou refusée : jamais visible hors auteur·rice et modération. */
    public function isUnderReview(): bool
    {
        return $this->currentStatus()?->isUnderReview() ?? false;
    }

    /**
     * État de relecture exposé par l'API : `pending`, `rejected`, `approved` (publiée après relecture) ou null (aucune
     * relecture : publication ordinaire, ou vidéo masquée / supprimée depuis).
     */
    public function reviewState(): ?string
    {
        return match ($this->currentStatus()) {
            PostStatus::Pending => 'pending',
            PostStatus::Rejected => 'rejected',
            PostStatus::Published => $this->reviewed_at !== null ? 'approved' : null,
            default => null,
        };
    }

    /**
     * Pseudo à afficher : jamais celui de l'auteur·rice d'une publication anonyme (RG-02).
     */
    public function authorLabel(): string
    {
        if ($this->is_anonymous || $this->user === null) {
            return (string) __('feed.post.anonymous_author');
        }

        return $this->user->pseudo;
    }
}
