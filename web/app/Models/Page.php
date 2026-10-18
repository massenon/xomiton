<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Page d'organisation (DATABASE_DICTIONARY.md §4 `pages`). Visible des autres membres seulement une fois **approuvée**
 * par la modération (`is_approved`, P8-03, P12-05) et active ; sa propriétaire la voit toujours (« En attente »).
 * `is_verified` (badge « Page vérifiée ») et `is_featured` (« En avant », P12-05) sont distincts de l'approbation.
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'name',
        'slug',
        'category',
        'description',
        'image_path',
        'cover_path',
        'website',
        'owner_id',
        'followers_count',
        'is_verified',
        'is_approved',
        'approved_at',
        'approved_by',
        'is_featured',
        'is_active',
    ];

    /** Image par défaut d'une page sans couverture (prototype : visuels hero-*). */
    public const DEFAULT_COVER = 'assets/images/xomiton/hero-09.jpg';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'followers_count' => 'integer',
            'is_verified' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<PageFollower, $this>
     */
    public function followings(): HasMany
    {
        return $this->hasMany(PageFollower::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'page_followers')->withPivot('created_at');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->where('is_verified', true);
    }

    /**
     * Approuvée par la modération (P12-05) : condition pour être vue des autres membres.
     *
     * @param  Builder<Page>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('is_approved', true);
    }

    /**
     * Pages lisibles par un·e membre : approuvées et actives, ou les siennes (même en attente ou désactivées).
     *
     * @param  Builder<Page>  $query
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $query->where(static function (Builder $q) use ($viewer): void {
            $q->where(static fn (Builder $r) => $r->where('is_approved', true)->where('is_active', true))
                ->orWhere('owner_id', $viewer->getKey());
        });
    }

    public function isPubliclyVisible(): bool
    {
        return $this->is_approved && $this->is_active && ! $this->trashed();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id !== null && (int) $this->owner_id === (int) $user->getKey();
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopeInCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }
}
