<?php

declare(strict_types=1);

namespace App\Application\Community\Groups;

use App\Enums\GroupPrivacy;
use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Listes de groupes (P8-02, prototype groups.php ; ADR-29 : lectures Eloquent directes) :
 *
 *  - « Découvrir » : groupes actifs **publics**, plus les groupes privés dont la personne est propriétaire ou membre
 *    active — un groupe privé n'apparaît jamais dans les listes d'une non-membre (FR-SOC-05, Q75) ;
 *  - « Mes groupes » : ceux dont elle est propriétaire ou membre active, plus ses demandes en attente ;
 *  - recherche « contient » sur le nom, la description et le département (prototype : nom, description, zone) ;
 *  - groupes de son département d'abord, puis les plus fréquentés, puis par nom.
 * `memberships()` charge en une requête l'adhésion de la personne pour une page de groupes (bouton Rejoindre / Membre /
 * Demande envoyée).
 */
final class GroupDirectory
{
    public const TAB_DISCOVER = 'discover';

    public const TAB_MINE = 'mine';

    /** @var list<string> */
    public const TABS = [self::TAB_DISCOVER, self::TAB_MINE];

    /**
     * @return LengthAwarePaginator<int, Group>
     */
    public function paginate(User $viewer, string $tab, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->query($viewer, $tab, $search)->paginate($perPage);
    }

    /**
     * @return Builder<Group>
     */
    public function query(User $viewer, string $tab, ?string $search = null): Builder
    {
        $id = (int) $viewer->getKey();
        $mine = GroupMember::query()->select('group_id')->where('user_id', $id);
        $search = trim((string) $search);

        $query = Group::query()->active()->with('zone');

        if ($tab === self::TAB_MINE) {
            $statuses = [MembershipStatus::Active->value, MembershipStatus::Requested->value];
            $query->where(static fn (Builder $q) => $q->where('owner_id', $id)
                ->orWhereIn('groups.id', (clone $mine)->whereIn('status', $statuses)));
        } else {
            $query->where(static fn (Builder $q) => $q->where('privacy', GroupPrivacy::Public)
                ->orWhere('owner_id', $id)
                ->orWhereIn('groups.id', (clone $mine)->where('status', MembershipStatus::Active->value)));
        }

        if ($search !== '') {
            $pattern = LikePattern::contains($search);
            $query->where(static fn (Builder $q) => $q->whereRaw(LikePattern::clause('groups.name'), [$pattern])
                ->orWhereRaw(LikePattern::clause('groups.description'), [$pattern])
                ->orWhereHas('zone', static fn (Builder $z) => $z->whereRaw(LikePattern::clause('zones.name'), [$pattern])));
        }

        // P12-05 : un groupe mis en avant par la modération passe en tête de liste.
        $query->orderByDesc('groups.is_featured');

        if ($viewer->zone_id !== null) {
            $query->orderByRaw('case when groups.zone_id = ? then 0 else 1 end', [(int) $viewer->zone_id]);
        }

        return $query->orderByDesc('members_count')->orderBy('name')->orderBy('groups.id');
    }

    public function membership(User $viewer, Group $group): ?GroupMember
    {
        return GroupMember::query()->where('group_id', $group->getKey())->where('user_id', $viewer->getKey())->first();
    }

    /**
     * Adhésions de la personne pour ces groupes, par identifiant de groupe.
     *
     * @param  iterable<Group>  $groups
     * @return array<int, GroupMember>
     */
    public function memberships(User $viewer, iterable $groups): array
    {
        $ids = [];

        foreach ($groups as $group) {
            $ids[] = (int) $group->getKey();
        }

        if ($ids === []) {
            return [];
        }

        return GroupMember::query()->where('user_id', $viewer->getKey())->whereIn('group_id', $ids)->get()
            ->keyBy(static fn (GroupMember $member): int => (int) $member->group_id)
            ->all();
    }

    /**
     * Nombre de demandes en attente du groupe (pastille de l'onglet « Membres » pour son équipe).
     */
    public function pendingRequests(Group $group): int
    {
        return GroupMember::query()->where('group_id', $group->getKey())->pending()->count();
    }
}
