<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\CategoryChips;
use App\Application\Content\Results\NewsListing;
use App\Models\Resource;
use App\Support\ReadingTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Liste publique `/actualites` (P4-10, prototype blog.php) : actualités = ressources `kind = article` publiées
 * (Q1 a, DATABASE_DICTIONARY.md `resources`), filtre catégorie (slug), tri `published_at` décroissant puis `id`,
 * « Charger plus » par la limite. Lecture seule (ADR-29).
 *
 * Puces (CategoryChips, règle Q60) : catégories actives `thematique` et `resource`, regroupées par slug, masquées sans
 * actualité publiée ; compteurs par requête groupée non cachée (une actualité programmée apparaît à son heure).
 * Catégorie d'une carte affichée seulement si elle est listée (jamais une catégorie inactive). Durée de lecture
 * (`reading_minutes`) calculée comme sur la fiche (ReadingTime : saisie, sinon estimée sur le corps), corps retiré ensuite.
 */
final class ListNews
{
    /** @var list<string> colonnes lues pour une carte (`body` pour la durée de lecture estimée, retiré ensuite) */
    private const CARD_COLUMNS = ['id', 'category_id', 'thematique_id', 'slug', 'title', 'kind', 'summary', 'body', 'image_path', 'duration_minutes', 'published_at'];

    public function __construct(private readonly CategoryChips $chips) {}

    /** null : catégorie inconnue ou inactive (404). */
    public function __invoke(?string $categorySlug, int $limit): ?NewsListing
    {
        /** @var array<int|string, int> $counts */
        $counts = Resource::query()->published()->news()
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        $chips = $this->chips->all($counts);
        $category = null;

        if ($categorySlug !== null) {
            $category = collect($chips)->firstWhere('slug', $categorySlug);

            if ($category === null) {
                return null;
            }
        }

        $totalAll = array_sum($counts);
        $total = $category === null ? $totalAll : $category['count'];
        $articles = $total === 0 ? new Collection : Resource::query()->published()->news()
            ->with(['category:id,kind,slug,label,icon,color', 'thematique:id,slug,cover_path,image_path'])
            ->when($category !== null, static fn (Builder $query) => $query->whereIn('category_id', $category['ids'] ?? []))
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(self::CARD_COLUMNS);

        $listedIds = CategoryChips::ids($chips);
        $articles->each(static function (Resource $article) use ($listedIds): void {
            if ($article->category_id !== null && ! in_array((int) $article->category_id, $listedIds, true)) {
                $article->setRelation('category', null);
            }

            $article->setAttribute('reading_minutes', ReadingTime::minutes($article->duration_minutes, $article->body));
            $article->offsetUnset('body');
        });

        return new NewsListing(CategoryChips::visible($chips), $category, $articles, $total, $totalAll, $limit);
    }
}
