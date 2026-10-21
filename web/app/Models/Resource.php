<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PurifiedHtml;
use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Support\LikePattern;
use App\Support\MediaUrl;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ressource documentaire ou actualité (DATABASE_DICTIONARY.md §3 `resources`). Le fichier téléchargeable (`file_path`)
 * vit sur le disque privé et n'est servi que par lien signé (`ressources.download` → `media.signed`, P4-03) ;
 * l'illustration (`image_path`) vit dans `public/assets` ou sur le disque public, jamais sur le disque privé.
 */
class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use HasFactory, SoftDeletes;

    /** Couverture par défaut (ni illustration ni thématique liée). */
    public const DEFAULT_COVER = 'assets/images/xomiton/covers/og-default.jpg';

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
        'og_image_path',
        'subtitles_path',
        'transcript',
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
            // HTML éditorial nettoyé à l'écriture (ADR-24, même profil que les thématiques) : la fiche l'affiche avec {!! !!}.
            'body' => PurifiedHtml::class,
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

    /**
     * Catalogue public `/ressources` (P4-03) : ressources publiées hors actualités (`article` vit sur `/actualites`, Q1).
     *
     * @param  Builder<\App\Models\Resource>  $query
     */
    public function scopeCatalog(Builder $query): void
    {
        $query->published()->whereNot('kind', ResourceKind::Article);
    }

    /**
     * Recherche « contient » sur le titre et le résumé, jokers LIKE de la saisie neutralisés (LikePattern).
     *
     * @param  Builder<\App\Models\Resource>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $pattern = LikePattern::contains($term);

        $query->where(static fn (Builder $where) => $where
            ->whereRaw(LikePattern::clause('title'), [$pattern])
            ->orWhereRaw(LikePattern::clause('summary'), [$pattern]));
    }

    /** Fichier à télécharger : type porté par un fichier (vidéo, audio, PDF) et fichier renseigné. */
    public function isDownloadable(): bool
    {
        return $this->kind->hasFile() && (string) $this->file_path !== '';
    }

    /**
     * Ressource qui renvoie vers un site externe (aucun fichier à télécharger) par une adresse web absolue `http(s)` :
     * `javascript:`, `data:` ou une adresse sans hôte ne sont jamais rendus en lien (l'échappement Blade ne les arrête
     * pas). L'admin (P12-03) et l'API (P6-08) valideront en plus `url:https` à l'écriture.
     */
    public function isExternal(): bool
    {
        return ! $this->isDownloadable() && self::isWebUrl($this->external_url);
    }

    private static function isWebUrl(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && (string) parse_url($url, PHP_URL_HOST) !== '';
    }

    /** Vidéo ou audio lisible dans la page (fichier hébergé). */
    public function isPlayable(): bool
    {
        return in_array($this->kind, [ResourceKind::Video, ResourceKind::Audio], true) && $this->isDownloadable();
    }

    /**
     * Couverture : illustration propre si le fichier existe, sinon couverture de la thématique liée, sinon l'image par
     * défaut. La thématique n'est lue que si l'illustration manque (listes : la charger avec `with('thematique')`).
     *
     * @return Attribute<string, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(function (): string {
            if (MediaUrl::exists($this->image_path)) {
                return MediaUrl::resolve($this->image_path, self::DEFAULT_COVER);
            }

            return $this->thematique_id !== null && $this->thematique !== null ? $this->thematique->cover_url : asset(self::DEFAULT_COVER);
        });
    }

    /**
     * Image de partage (Open Graph 1200 × 630) : image dédiée de la fiche (P12-03) si son fichier existe, sinon la couverture.
     *
     * @return Attribute<string, never>
     */
    protected function ogUrl(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::exists($this->og_image_path) ? MediaUrl::resolve($this->og_image_path, $this->cover_url) : $this->cover_url);
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }
}
