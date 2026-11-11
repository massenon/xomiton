<?php

declare(strict_types=1);

namespace App\Application\Community\Groups;

use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\Follow;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;

/**
 * Onglet « Membres » d'un groupe (P8-02, group-details.php l.82-97 ; `GET /api/v1/groups/{ulid}/members`) :
 *
 *  - membres actives, équipe d'abord (propriétaire, admins, modérateur·rices), puis par date d'arrivée ;
 *  - pour une membre ordinaire, seulement les personnes dont le profil lui est visible (même règle que
 *    App\Domain\Account\ProfileAudience : `public` / `community` → tout le réseau, `followers` → ses abonné·es,
 *    `private` → personne ; ni compte suspendu ou anonymisé, ni blocage dans un sens ou dans l'autre) : appartenir à un
 *    groupe peut révéler une identité (RG-02) ; les autres sont comptées (`hidden_count`) sans être nommées ;
 *  - l'équipe du groupe voit toutes les membres et, avec `status=requested`, les demandes en attente.
 */
final class GroupMembersQuery
{
    /**
     * @return Builder<GroupMember>
     */
    public function for(Group $group, User $viewer, bool $team, MembershipStatus $status = MembershipStatus::Active): Builder
    {
        $query = GroupMember::query()
            ->where('group_members.group_id', $group->getKey())
            ->where('group_members.status', $status)
            ->with(['user.profile', 'user.roles']);

        if (! $team) {
            $query->whereIn('group_members.user_id', $this->visibleUsers($viewer));
        }

        $owner = (int) ($group->owner_id ?? 0);

        return $query
            ->orderByRaw('case when group_members.user_id = ? then 0 when group_members.role = ? then 1 when group_members.role = ? then 2 else 3 end', [$owner, GroupRole::Admin->value, GroupRole::Moderator->value])
            ->orderBy('group_members.joined_at')
            ->orderBy('group_members.id');
    }

    /**
     * Membres actives que la personne ne voit pas (profil non visible, blocage).
     */
    public function hiddenCount(Group $group, User $viewer): int
    {
        $total = GroupMember::query()->where('group_id', $group->getKey())->where('status', MembershipStatus::Active)->count();
        $visible = GroupMember::query()->where('group_id', $group->getKey())->where('status', MembershipStatus::Active)
            ->whereIn('user_id', $this->visibleUsers($viewer))->count();

        return max(0, $total - $visible);
    }

    /**
     * @return Builder<User>
     */
    private function visibleUsers(User $viewer): Builder
    {
        $id = (int) $viewer->getKey();
        $following = Follow::query()->select('followed_id')->where('follower_id', $id);

        return User::query()->select('users.id')
            ->where(static function (Builder $q) use ($id, $following): void {
                $q->whereKey($id)->orWhere(static function (Builder $r) use ($id, $following): void {
                    $r->where('users.status', '!=', UserStatus::Suspended)
                        ->notAnonymized()
                        ->whereNotIn('users.id', UserBlock::query()->select('blocked_user_id')->where('user_id', $id))
                        ->whereNotIn('users.id', UserBlock::query()->select('user_id')->where('blocked_user_id', $id))
                        ->where(static fn (Builder $v) => $v->whereDoesntHave('profile')
                            ->orWhereHas('profile', static fn (Builder $p) => $p->whereNull('visibility')
                                ->orWhereIn('visibility', [ProfileVisibility::Public, ProfileVisibility::Community]))
                            ->orWhere(static fn (Builder $f) => $f->whereHas('profile', static fn (Builder $p) => $p->where('visibility', ProfileVisibility::Followers))
                                ->whereIn('users.id', $following)));
                });
            });
    }
}
