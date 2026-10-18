<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PurifiedHtml;
use App\Casts\PurifiedSections;
use App\Enums\ContentStatus;
use App\Support\MediaUrl;
use Database\Factories\ThematiqueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fiche thématique publique identifiée par son slug (DATABASE_DICTIONARY.md §3 `thematiques`). `body` et `sections[].body`
 * sont du HTML nettoyé à l'écriture (profil purifier `content`, DEVELOPMENT_STANDARDS §7) : les vues l'affichent tel quel.
 */
class Thematique extends Model
{
    /** @use HasFactory<ThematiqueFactory> */
    use HasFactory, SoftDeletes;

    /** Couverture affichée quand `cover_path` et `image_path` sont vides ou pointent sur un fichier absent. */
    public const DEFAULT_COVER = 'assets/images/xomiton/covers/og-default.jpg';

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'slug',
        'title',
        'summary',
        'body',
        'sections',
        'content_warning',
        'image_path',
        'cover_path',
        'reading_minutes',
        'related_contact_roles',
        'related_ids',
        'status',
        'published_at',
        'views_count',
        'meta_title',
        'meta_description',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'body' => PurifiedHtml::class,
            'sections' => PurifiedSections::class,
            'related_contact_roles' => 'array',
            'related_ids' => 'array',
            'reading_minutes' => 'integer',
            'views_count' => 'integer',
            'status' => ContentStatus::class,
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

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<\App\Models\Resource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    /**
     * Thématiques visibles du public : publiées et dont la date de publication est passée.
     *
     * @param  Builder<Thematique>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** @param  Builder<Thematique>  $query */
    public function scopeInCategory(Builder $query, Category|int $category): void
    {
        $query->where('category_id', $category instanceof Category ? $category->getKey() : $category);
    }

    /** @param  Builder<Thematique>  $query */
    public function scopeLatestPublished(Builder $query): void
    {
        $query->orderByDesc('published_at')->orderByDesc('id');
    }

    /**
     * Thématiques liées (`related_ids`), dans l'ordre du tableau.
     *
     * @return Builder<Thematique>
     */
    public function related(): Builder
    {
        $ids = array_values(array_filter(array_map('intval', $this->related_ids ?? [])));

        return static::query()->whereIn('id', $ids);
    }

    /** Le corps s'ouvre sur son propre chapeau (`<p class="lead">`) : la fiche n'affiche alors pas le résumé en chapeau. */
    public function bodyOpensWithLead(): bool
    {
        return preg_match('/^\s*<p\s+class="(?:[^"]*\s)?lead(?:\s[^"]*)?"/i', (string) $this->body) === 1;
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }

    /**
     * URL de la couverture : `cover_path` si le fichier existe, sinon l'illustration, sinon l'image par défaut.
     *
     * @return Attribute<string, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $fallback = MediaUrl::exists($this->image_path) ? (string) $this->image_path : self::DEFAULT_COVER;

            return MediaUrl::resolve($this->cover_path, $fallback);
        });
    }

    /**
     * URL de l'illustration de la fiche, avec repli sur l'image par défaut.
     *
     * @return Attribute<string, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::resolve($this->image_path, self::DEFAULT_COVER));
    }
}
