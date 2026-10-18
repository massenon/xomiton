<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\CategoryChips;
use App\Application\Content\Results\NewsSheet;
use App\Models\Category;
use App\Models\Resource;
use App\Support\ReadingTime;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Fiche publique d'une actualité `/actualites/{slug}` (P4-10, prototype blog-details.php). Lecture seule (ADR-29).
 *
 *   - ressource publiée (statut, date passée), non supprimée, sinon ModelNotFoundException (404), sans liaison
 *     implicite ; une ressource d'un autre type est rendue sans compléments : le contrôleur la redirige vers
 *     `/ressources/{slug}` (Q1) ;
 *   - catégorie (fil d'Ariane, badge, articleSection) seulement si elle est listée (CategoryChips) ;
 *   - « À lire aussi » (prototype l.53-62) : les autres actualités publiées, les plus récentes.
 */
final class ShowNews
{
    /** @var list<string> */
    private const OTHER_COLUMNS = ['id', 'thematique_id', 'slug', 'title', 'image_path', 'published_at'];

    public function __construct(private readonly CategoryChips $chips) {}

    /** @throws ModelNotFoundException<\App\Models\Resource> */
    public function __invoke(string $slug): NewsSheet
    {
        $article = Resource::query()->published()->where('slug', $slug)->with(['category', 'thematique'])->firstOrFail();
        $sheet = new NewsSheet($article, null, new Collection);

        if (! $sheet->isNews()) {
            return $sheet;
        }

        $category = $this->listedCategory($article);
        $article->setRelation('category', $category);

        return new NewsSheet($article, $category, $this->others($article), ReadingTime::minutes($article->duration_minutes, $article->body));
    }

    private function listedCategory(Resource $article): ?Category
    {
        $category = $article->category;

        return $category !== null && in_array((int) $category->getKey(), CategoryChips::ids($this->chips->all([])), true) ? $category : null;
    }

    /** @return EloquentCollection<int, \App\Models\Resource> */
    private function others(Resource $article): EloquentCollection
    {
        return Resource::query()->published()->news()
            ->whereKeyNot($article->getKey())
            ->with('thematique:id,slug,cover_path,image_path')
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->limit(max(0, (int) config('xomiton.content.news_related_count', 4)))
            ->get(self::OTHER_COLUMNS);
    }
}
