<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\Results\ResourceSheet;
use App\Application\Shared\Referentials;
use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Fiche publique d'une ressource `/ressources/{slug}` (P4-03). Lecture seule (ADR-29).
 *
 *   - ressource : publiée (statut, date passée), non supprimée, sinon ModelNotFoundException (404) — pas de liaison
 *     implicite, qui servirait aussi brouillons et ressources programmées ; une actualité (`article`) est rendue sans
 *     blocs liés : le contrôleur la redirige vers `/actualites/{slug}` (Q1) ;
 *   - catégorie (fil d'Ariane, badge, mention juridique, JSON-LD) : seulement si elle est une puce de la liste (active,
 *     `thematique` ou `resource`), jamais de lien vers un `?cat=` en 404 ni de badge d'une catégorie inactive ;
 *   - thématique liée : seulement publiée ;
 *   - ressources liées : même catégorie d'abord, puis les autres, les plus récentes (hors actualités, courante exclue).
 */
final class ShowResource
{
    /** @var list<string> */
    private const RELATED_COLUMNS = ['id', 'category_id', 'thematique_id', 'slug', 'title', 'kind', 'file_path', 'file_size', 'external_url', 'image_path', 'duration_minutes', 'published_at'];

    public function __construct(private readonly Referentials $referentials) {}

    /** @throws ModelNotFoundException<\App\Models\Resource> */
    public function __invoke(string $slug): ResourceSheet
    {
        $resource = Resource::query()->published()->where('slug', $slug)->with(['category', 'thematique'])->firstOrFail();

        if ($resource->kind === ResourceKind::Article) {
            return new ResourceSheet($resource, null, null, new Collection);
        }

        $thematique = $resource->thematique;
        $category = $this->listedCategory($resource);
        // Badge, mention juridique et JSON-LD lisent la même catégorie que le fil d'Ariane (jamais une catégorie inactive).
        $resource->setRelation('category', $category);

        return new ResourceSheet(
            $resource,
            $category,
            $thematique !== null && $thematique->isPublished() ? $thematique : null,
            $this->related($resource),
        );
    }

    private function listedCategory(Resource $resource): ?Category
    {
        $category = $resource->category;
        $listed = $category !== null && collect($this->referentials->categories())
            ->contains(static fn (array $row): bool => $row['id'] === (int) $category->getKey()
                && in_array($row['kind'], [Category::KIND_THEMATIQUE, Category::KIND_RESOURCE], true));

        return $listed ? $category : null;
    }

    /** @return Collection<int, \App\Models\Resource> */
    private function related(Resource $resource): Collection
    {
        $wanted = max(0, (int) config('xomiton.content.related_count', 3));
        $related = new Collection;

        foreach ($resource->category_id === null ? [false] : [true, false] as $sameCategory) {
            $missing = $wanted - $related->count();
            if ($missing <= 0) {
                break;
            }

            $query = Resource::query()->catalog()
                ->whereKeyNot([(int) $resource->getKey(), ...$related->map(static fn (Resource $item): int => (int) $item->getKey())->all()]);

            if ($resource->category_id !== null) {
                $query->where(static fn (Builder $where) => $sameCategory
                    ? $where->where('category_id', $resource->category_id)
                    : $where->where('category_id', '!=', $resource->category_id)->orWhereNull('category_id'));
            }

            $related = $related->concat($query->orderByDesc('published_at')->orderBy('id')->limit($missing)->get(self::RELATED_COLUMNS));
        }

        // Une seule requête pour les couvertures de repli (thématique), quel que soit le nombre de passes.
        return (new EloquentCollection($related->values()->all()))->load('thematique:id,slug,cover_path,image_path');
    }
}
