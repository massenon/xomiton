<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ressource documentaire ou actualité (DATABASE_DICTIONARY.md §3 `resources`).
 */
class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'thematique_id',
        'slug',
        'title',
        'kind',
        'summary',
        'body',
        'file_path',
        'file_size',
        'external_url',
        'image_path',
        'duration_minutes',
        'status',
        'published_at',
        'downloads_count',
        'views_count',
        'is_offline_available',
        'meta_title',
        'meta_description',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => ResourceKind::class,
            'status' => ContentStatus::class,
            'file_size' => 'integer',
            'duration_minutes' => 'integer',
            'downloads_count' => 'integer',
            'views_count' => 'integer',
            'is_offline_available' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Thematique, $this> */
    public function thematique(): BelongsTo
    {
        return $this->belongsTo(Thematique::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param  Builder<\App\Models\Resource>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** @param  Builder<\App\Models\Resource>  $query */
    public function scopeOfKind(Builder $query, ResourceKind $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * Actualités (`/actualites`) : ressources de type article, décision provisoire du dictionnaire.
     *
     * @param  Builder<\App\Models\Resource>  $query
     */
    public function scopeNews(Builder $query): void
    {
        $query->where('kind', ResourceKind::Article);
    }

    /** @param  Builder<\App\Models\Resource>  $query */
    public function scopeOfflineAvailable(Builder $query): void
    {
        $query->where('is_offline_available', true);
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }
}
