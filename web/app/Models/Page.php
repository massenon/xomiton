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
 * Page d'organisation (DATABASE_DICTIONARY.md §4 `pages`).
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
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'followers_count' => 'integer',
            'is_verified' => 'boolean',
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
     * @param  Builder<Page>  $query
     */
    public function scopeInCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }
}
