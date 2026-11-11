<?php

declare(strict_types=1);

namespace App\Application\Community\Pages;

use App\Models\Page;
use App\Models\PageFollower;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Listes de pages (P8-03, pages.php ; ADR-29) : pages approuvées et actives (une page en attente n'apparaît que pour
 * sa propriétaire, dans « Mes pages »), filtre par catégorie (`category`) et recherche « contient » sur le nom et la
 * description (prototype), onglets Toutes · Abonnements · Mes pages ; mises en avant d'abord (P12-05), puis les plus
 * suivies, puis par nom. `followed()` lit en une requête les abonnements de la personne pour une page de résultats.
 */
final class PageDirectory
{
    public const TAB_ALL = 'all';

    public const TAB_FOLLOWING = 'following';

    public const TAB_MINE = 'mine';

    /** @var list<string> */
    public const TABS = [self::TAB_ALL, self::TAB_FOLLOWING, self::TAB_MINE];

    /**
     * @return LengthAwarePaginator<int, Page>
     */
    public function paginate(User $viewer, string $tab, ?string $category, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->query($viewer, $tab, $category, $search)->paginate($perPage);
    }

    /**
     * @return Builder<Page>
     */
    public function query(User $viewer, string $tab, ?string $category = null, ?string $search = null): Builder
    {
        $id = (int) $viewer->getKey();
        $search = trim((string) $search);

        $query = match ($tab) {
            self::TAB_MINE => Page::query()->where('owner_id', $id),
            self::TAB_FOLLOWING => Page::query()->approved()->active()
                ->whereIn('pages.id', PageFollower::query()->select('page_id')->where('user_id', $id)),
            default => Page::query()->approved()->active(),
        };

        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }

        if ($search !== '') {
            $pattern = LikePattern::contains($search);
            $query->where(static fn (Builder $q) => $q->whereRaw(LikePattern::clause('pages.name'), [$pattern])
                ->orWhereRaw(LikePattern::clause('pages.description'), [$pattern]));
        }

        return $query->orderByDesc('is_featured')->orderByDesc('followers_count')->orderBy('name')->orderBy('pages.id');
    }

    /**
     * Identifiants des pages suivies par la personne parmi celles-ci.
     *
     * @param  iterable<Page>  $pages
     * @return array<int, true>
     */
    public function followed(User $viewer, iterable $pages): array
    {
        $ids = [];

        foreach ($pages as $page) {
            $ids[] = (int) $page->getKey();
        }

        if ($ids === []) {
            return [];
        }

        return PageFollower::query()->where('user_id', $viewer->getKey())->whereIn('page_id', $ids)->pluck('page_id')
            ->mapWithKeys(static fn ($id): array => [(int) $id => true])->all();
    }

    /**
     * Catégories du filtre (pages.php : celles des pages visibles), dans l'ordre de `xomiton.community.page_categories`
     * puis par ordre alphabétique.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        $configured = self::choices();
        $used = Page::query()->approved()->active()->distinct()->orderBy('category')->pluck('category')
            ->map(static fn ($category): string => (string) $category)->all();

        return array_values(array_unique([...array_values(array_intersect($configured, $used)), ...$used]));
    }

    /**
     * Catégories proposées à la création (create-page.php $cats).
     *
     * @return list<string>
     */
    public static function choices(): array
    {
        return array_values(array_filter((array) config('xomiton.community.page_categories', []), 'is_string'));
    }
}
