<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CccKind;
use Database\Factories\CccToolFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outil du kit CCC numérisé (DATABASE_DICTIONARY.md §3 `ccc_tools`) : `chapters` pour un module du manuel,
 * `pages` (planches `{image, caption, alt}`) pour une boîte à images ou une bande dessinée. P19 : `is_public` (BD servie
 * aussi sur la page publique indexable `/bd/{key}`, Q19), `thematique_id` (fiche de connaissances liée) et `feed_post_id`
 * (publication du fil qui a annoncé la BD : marqueur d'idempotence de PublishComicsToFeed).
 */
class CccTool extends Model
{
    /** @use HasFactory<CccToolFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'kind',
        'key',
        'title',
        'description',
        'image_path',
        'pages',
        'chapters',
        'duration_minutes',
        'position',
        'is_published',
        'is_public',
        'thematique_id',
        'feed_post_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => CccKind::class,
            'pages' => 'array',
            'chapters' => 'array',
            'duration_minutes' => 'integer',
            'position' => 'integer',
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'thematique_id' => 'integer',
            'feed_post_id' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /** @param  Builder<CccTool>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * BD visibles du public (Q19, P19-04) : publiées **et** publiques, de type `bd`.
     *
     * @param  Builder<CccTool>  $query
     */
    public function scopePublicComics(Builder $query): void
    {
        $query->where('is_published', true)->where('is_public', true)->where('kind', CccKind::Comic->value);
    }

    /** @return BelongsTo<Thematique, $this> */
    public function thematique(): BelongsTo
    {
        return $this->belongsTo(Thematique::class);
    }

    /** @return BelongsTo<Post, $this> */
    public function feedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'feed_post_id');
    }

    /** @param  Builder<CccTool>  $query */
    public function scopeOfKind(Builder $query, CccKind $kind): void
    {
        $query->where('kind', $kind);
    }

    /** @param  Builder<CccTool>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('key');
    }

    public function pagesCount(): int
    {
        return count($this->pages ?? []);
    }
}
