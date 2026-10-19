<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Application\Community\Network\NetworkPeople;
use App\Application\Directory\BrowseDirectory;
use App\Application\Directory\DirectoryFilters;
use App\Application\Shared\Referentials;
use App\Domain\Search\Exceptions\SearchUnavailable;
use App\Domain\Search\SearchTerm;
use App\Domain\Search\SearchType;
use App\Models\Category;
use App\Models\Follow;
use App\Models\Resource as CatalogResource;
use App\Models\Thematique;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Throwable;

/**
 * Recherche membre de l'en-tête (P8-13 ; XmHeader « Rechercher une ressource, un contact… », prototype
 * partials/header.php l.30-33) : page `/recherche`, `GET /api/v1/search` et `search/suggestions`. Lecture seule, sur
 * les **colonnes en clair** seulement (ADR-33 : jamais une colonne chiffrée, jamais de recherche plein texte sur un
 * récit), en réutilisant les règles de chaque écran :
 *  - ressources : catalogue de `/ressources` (Resource::catalog + scopeSearch : titre, résumé) ; catégorie affichée
 *    seulement si elle est un filtre de la liste (même règle que ListResources) ;
 *  - thématiques : fiches publiées de `/thematiques` (titre, résumé ; catégorie : ThematiqueCardResource::listed) ;
 *  - contacts : BrowseDirectory (portée DirectoryReach : fiches publiques pour un·e membre, réseau compris pour pair·e
 *    validé·e, modération, administration ; nom, organisation, libellé du rôle, commune) ;
 *  - membres : comptes **trouvables** (NetworkPeople::discoverableBy : actifs, profil ouvert au réseau, réglage
 *    « Trouvable par recherche », sans blocage dans un sens ou dans l'autre, jamais un rôle caché) par pseudonyme —
 *    jamais le nom réel, l'e-mail ni le téléphone.
 *
 * Confidentialité : **aucun historique** — le terme (App\Domain\Search\SearchTerm) n'est ni enregistré, ni journalisé,
 * ni mis en cache ; une erreur de base (dont le message recopierait la requête et ses liaisons) devient
 * SearchUnavailable, sans message ni exception d'origine (classe et SQLSTATE seulement, signalés par la page et l'API). Un terme plus court que `xomiton.search.min_length` ne lance
 * aucune requête.
 */
final class MemberSearch
{
    public function __construct(
        private readonly BrowseDirectory $directory,
        private readonly NetworkPeople $people,
        private readonly Referentials $referentials,
    ) {}

    public static function minLength(): int
    {
        return max(1, (int) config('xomiton.search.min_length', 2));
    }

    /**
     * Familles ouvertes au compte (contacts : `directory.view` ; membres : accès au réseau), dans l'ordre d'affichage.
     *
     * @return list<SearchType>
     */
    public function typesFor(User $viewer): array
    {
        return SearchType::availableTo(
            BrowseDirectory::reachOf($viewer)->canBrowse(),
            $viewer->can('viewAny', Follow::class),
        );
    }

    /**
     * Vue d'ensemble : chaque famille ouverte au compte, ses `$perType` premiers résultats et son total.
     *
     * @return list<SearchSection>
     *
     * @throws SearchUnavailable
     */
    public function overview(User $viewer, SearchTerm $term, int $perType): array
    {
        $types = $this->typesFor($viewer);

        if (! $term->isSearchable(self::minLength())) {
            return array_map(static fn (SearchType $type): SearchSection => SearchSection::empty($type), $types);
        }

        return $this->guarded(fn (): array => array_map(
            fn (SearchType $type): SearchSection => SearchSection::fromPage($type, $this->paginate($viewer, $type, $term, max(1, $perType), 1)),
            $types,
        ));
    }

    /**
     * Une famille, page par page (« Charger plus »). Famille fermée au compte ou terme trop court : page vide.
     *
     * @return LengthAwarePaginator<int, \Illuminate\Database\Eloquent\Model>
     *
     * @throws SearchUnavailable
     */
    public function page(User $viewer, SearchTerm $term, SearchType $type, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);

        if (! in_array($type, $this->typesFor($viewer), true) || ! $term->isSearchable(self::minLength())) {
            return new Paginator([], 0, $perPage, 1);
        }

        return $this->guarded(fn (): LengthAwarePaginator => $this->paginate($viewer, $type, $term, $perPage, max(1, $page)));
    }

    /**
     * @return LengthAwarePaginator<int, \Illuminate\Database\Eloquent\Model>
     */
    private function paginate(User $viewer, SearchType $type, SearchTerm $term, int $perPage, int $page): LengthAwarePaginator
    {
        $q = $term->value();

        return match ($type) {
            SearchType::Resources => $this->resources($q, $perPage, $page),
            SearchType::Thematiques => $this->thematiques($q, $perPage, $page),
            SearchType::Contacts => $this->directory->page($viewer, new DirectoryFilters(q: $q), $perPage, $page),
            SearchType::Members => $this->members($viewer, $q)->paginate($perPage, ['*'], 'page', $page),
        };
    }

    /**
     * @return LengthAwarePaginator<int, CatalogResource>
     */
    private function resources(string $q, int $perPage, int $page): LengthAwarePaginator
    {
        $paginator = CatalogResource::query()->catalog()->search($q)
            ->with(['category:id,kind,slug,label,icon,color', 'thematique:id,slug,cover_path,image_path'])
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->paginate($perPage, ['id', 'category_id', 'thematique_id', 'slug', 'title', 'kind', 'summary', 'file_path', 'file_size', 'external_url', 'image_path', 'duration_minutes', 'is_offline_available', 'published_at'], 'page', $page);

        // Catégorie affichée seulement si c'est un filtre de /ressources (catégorie active de type thématique ou
        // ressource), comme ListResources : jamais un badge qui mènerait à un 404.
        return $this->withListedCategory($paginator, [Category::KIND_THEMATIQUE, Category::KIND_RESOURCE]);
    }

    /**
     * @return LengthAwarePaginator<int, Thematique>
     */
    private function thematiques(string $q, int $perPage, int $page): LengthAwarePaginator
    {
        $pattern = LikePattern::contains($q);

        $paginator = Thematique::query()->published()
            ->with('category:id,slug,label,icon,color')
            ->where(static fn (Builder $where) => $where
                ->whereRaw(LikePattern::clause('title'), [$pattern])
                ->orWhereRaw(LikePattern::clause('summary'), [$pattern]))
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->paginate($perPage, ['id', 'category_id', 'slug', 'title', 'summary', 'cover_path', 'image_path', 'reading_minutes', 'published_at'], 'page', $page);

        // Catégorie affichée seulement si c'est un filtre de /thematiques (même règle que ThematiqueCardResource::listed).
        return $this->withListedCategory($paginator, [Category::KIND_THEMATIQUE]);
    }

    /**
     * Membres trouvables dont le pseudonyme contient le terme, par ordre alphabétique.
     *
     * @return Builder<User>
     */
    private function members(User $viewer, string $q): Builder
    {
        return $this->people->discoverableBy($viewer)
            ->with(['profile', 'roles', 'zone'])
            ->whereRaw(LikePattern::clause('users.pseudo'), [LikePattern::contains($q)])
            ->orderBy('users.pseudo')
            ->orderBy('users.id');
    }

    /**
     * Retire la catégorie des fiches dont la catégorie n'est pas active et de l'un de ces types (référentiel en cache).
     *
     * @template TPage of LengthAwarePaginator
     *
     * @param  TPage  $paginator
     * @param  list<string>  $kinds
     * @return TPage
     */
    private function withListedCategory(LengthAwarePaginator $paginator, array $kinds): LengthAwarePaginator
    {
        $listed = array_values(array_map(
            static fn (array $category): int => (int) $category['id'],
            array_filter($this->referentials->categories(), static fn (array $category): bool => in_array($category['kind'], $kinds, true)),
        ));

        foreach ($paginator->items() as $item) {
            if ($item->category_id === null || ! in_array((int) $item->category_id, $listed, true)) {
                $item->setRelation('category', null);
            }
        }

        return $paginator;
    }

    /**
     * Exécute la recherche ; toute erreur devient SearchUnavailable **sans** l'exception d'origine (son message et sa
     * trace porteraient le terme jusqu'au journal) : seules sa classe et son SQLSTATE sont gardés pour le signalement.
     *
     * @template T
     *
     * @param  callable(): T  $search
     * @return T
     *
     * @throws SearchUnavailable
     */
    private function guarded(callable $search): mixed
    {
        try {
            return $search();
        } catch (Throwable $e) {
            throw SearchUnavailable::from($e);
        }
    }
}
