<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ForumFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Forum thématique (DATABASE_DICTIONARY.md §4 `forums`) ; la clé courte `f1`… vient du prototype.
 */
class Forum extends Model
{
    /** @use HasFactory<ForumFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'slug',
        'description',
        'icon',
        'position',
        'topics_count',
        'posts_count',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'topics_count' => 'integer',
            'posts_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ForumTopic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(ForumTopic::class);
    }

    /**
     * @param  Builder<Forum>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Forum>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    /**
     * Forum par clé courte du prototype (`f1`…). Nommé `forKey` car `whereKey()` est réservé par
     * le Builder Eloquent (clé primaire) et masquerait le scope.
     *
     * @param  Builder<Forum>  $query
     */
    public function scopeForKey(Builder $query, string $key): void
    {
        $query->where('key', $key);
    }
}
