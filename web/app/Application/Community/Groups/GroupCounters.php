<?php

declare(strict_types=1);

namespace App\Application\Community\Groups;

use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;

/**
 * Compteurs dénormalisés des groupes (DATABASE_DICTIONARY.md §4 : `members_count`, `posts_count` recalculés par les
 * actions), comme App\Application\Feed\FeedCounters : incréments atomiques (`UPDATE … SET n = n + 1`) dans la
 * transaction de l'action, jamais sous zéro, sur le Query Builder (un compteur ne touche pas `updated_at`).
 * `posts_count` : publications non supprimées du groupe (publiées ou masquées par la modération, masquage réversible ;
 * l'onglet Publications ne montre que les publiées). `recountMembers()` recalcule depuis `group_members` (reprise après incident ; les groupes importés du prototype
 * gardent leur compteur d'origine tant qu'on ne le recalcule pas).
 */
final class GroupCounters
{
    public function increment(Group $group, string $column = 'members_count'): void
    {
        Group::query()->whereKey($group->getKey())->toBase()->increment($column);
        $group->setAttribute($column, (int) $group->getAttribute($column) + 1);
    }

    public function decrement(Group $group, string $column = 'members_count'): void
    {
        Group::query()->whereKey($group->getKey())->where($column, '>=', 1)->toBase()->decrement($column);
        $group->setAttribute($column, max(0, (int) $group->getAttribute($column) - 1));
    }

    public function recountMembers(Group $group): int
    {
        $count = GroupMember::query()->where('group_id', $group->getKey())->where('status', MembershipStatus::Active)->count();

        Group::query()->whereKey($group->getKey())->toBase()->update(['members_count' => $count]);
        $group->setAttribute('members_count', $count);

        return $count;
    }
}
