<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Application\Shared\Referentials;
use App\Domain\Directory\DirectoryReach;
use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Lecture de l'annuaire membre `/annuaire` et `GET /api/v1/directory` (P8-12 ; prototype annuaire.php l.29-41) : fiches
 * actives selon la portée de la personne connectée (DirectoryReach : publiques pour un·e membre, réseau compris pour
 * pair·e validé·e, modération et administration), filtres rôle · département · commune · recherche (nom, organisation,
 * libellé du rôle, commune), ordre alphabétique ; décompte par département pour la carte schématique (mêmes filtres,
 * sans le lieu : une pastille annonce ce que son clic affichera). Lecture seule.
 */
final class BrowseDirectory
{
    public function __construct(private readonly Referentials $referentials) {}

    /** Portée de l'annuaire pour ce compte (ROLE_ET_PERMISSIONS.md §3 ; RG-03 : pair·e en attente = membre). */
    public static function reachOf(User $viewer): DirectoryReach
    {
        return DirectoryReach::of(
            $viewer->can('directory.view'),
            $viewer->isActive(),
            $viewer->can('peer_space.access') || $viewer->can('admin.access'),
        );
    }

    /**
     * @return LengthAwarePaginator<int, DirectoryContact>
     */
    public function page(User $viewer, DirectoryFilters $filters, int $perPage, int $page = 1): LengthAwarePaginator
    {
        return $this->query(self::reachOf($viewer), $filters)
            ->with(['zone:id,name', 'commune:id,name'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    /**
     * Carte schématique par département (prototype annuaire.php l.38-41, l.73-78) : chaque département actif (référentiel
     * en cache, dans son ordre) avec son nombre de fiches, fiches nationales (sans département) à part ; mêmes filtres
     * que la liste hors département et commune.
     *
     * @return array{zones: list<array{id: int, name: string, slug: string, count: int}>, national: int, total: int}
     */
    public function map(User $viewer, DirectoryFilters $filters): array
    {
        $counts = [];
        $national = 0;

        $rows = $this->query(self::reachOf($viewer), $filters->withoutPlace())
            ->toBase()
            ->selectRaw('zone_id, count(*) as aggregate')
            ->groupBy('zone_id')
            ->get();

        foreach ($rows as $row) {
            if ($row->zone_id === null) {
                $national += (int) $row->aggregate;
            } else {
                $counts[(int) $row->zone_id] = (int) $row->aggregate;
            }
        }

        $zones = array_map(static fn (array $zone): array => [
            'id' => $zone['id'],
            'name' => $zone['name'],
            'slug' => $zone['slug'],
            'count' => $counts[$zone['id']] ?? 0,
        ], $this->referentials->zones());

        return ['zones' => $zones, 'national' => $national, 'total' => array_sum($counts) + $national];
    }

    /**
     * @return Builder<DirectoryContact>
     */
    private function query(DirectoryReach $reach, DirectoryFilters $filters): Builder
    {
        $query = DirectoryContact::query()->active();

        if (! $reach->canBrowse()) {
            return $query->whereRaw('1 = 0');
        }

        if (! $reach->seesNonPublic()) {
            $query->public();
        }

        if ($filters->role !== null) {
            $query->withRole($filters->role);
        }

        if ($filters->zoneId !== null) {
            $query->where('zone_id', $filters->zoneId);

            if ($filters->communeId !== null) {
                $query->where('commune_id', $filters->communeId);
            }
        }

        if ($filters->q !== null && $filters->q !== '') {
            $this->search($query, $filters->q);
        }

        return $query;
    }

    /**
     * Recherche « contient » du prototype (nom, organisation, rôle, commune) ; le rôle est cherché par son libellé, sans
     * tenir compte des accents ni de la casse (« gyneco » trouve « Gynécologue »).
     *
     * @param  Builder<DirectoryContact>  $query
     */
    private function search(Builder $query, string $q): void
    {
        $pattern = LikePattern::contains($q);
        $needle = self::folded($q);
        $roles = array_values(array_map(
            static fn (DirectoryRole $role): string => $role->value,
            array_filter(DirectoryRole::cases(), static fn (DirectoryRole $role): bool => str_contains(self::folded($role->label()), $needle)),
        ));

        $query->where(static function (Builder $scope) use ($pattern, $roles): void {
            $scope->whereRaw(LikePattern::clause('directory_contacts.name'), [$pattern])
                ->orWhereRaw(LikePattern::clause('directory_contacts.org'), [$pattern])
                ->orWhereHas('commune', static fn (Builder $commune) => $commune->whereRaw(LikePattern::clause('communes.name'), [$pattern]));

            if ($roles !== []) {
                $scope->orWhereIn('role', $roles);
            }
        });
    }

    /** Libellé comparable : sans accents (Str::ascii), en minuscules. */
    private static function folded(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }
}
