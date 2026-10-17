<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\GroupPrivacy;
use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

/**
 * Groupes du réseau social : `social.access` lit les groupes publics et rejoint ; un groupe privé n'est visible
 * que de ses membres actifs ; propriétaire et administrateur·rices du groupe gèrent ; `moderation.manage` modère tout.
 */
final class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function view(User $user, Group $group): bool
    {
        if ($user->can('moderation.manage')) {
            return true;
        }

        if (! $user->can('social.access')) {
            return false;
        }

        if ($group->privacy === GroupPrivacy::Public && $group->is_active) {
            return true;
        }

        return $this->isOwner($user, $group) || $this->membership($user, $group)?->isActive() === true;
    }

    public function create(User $user): bool
    {
        return $user->can('social.access');
    }

    public function update(User $user, Group $group): bool
    {
        return $user->can('moderation.manage') || $this->isOwner($user, $group) || $this->hasRole($user, $group, admin: true);
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->can('moderation.manage') || $this->isOwner($user, $group);
    }

    public function join(User $user, Group $group): bool
    {
        if (! $user->can('social.access') || ! $group->is_active) {
            return false;
        }

        $membership = $this->membership($user, $group);

        return $membership === null || $membership->status === MembershipStatus::Requested;
    }

    public function leave(User $user, Group $group): bool
    {
        return ! $this->isOwner($user, $group) && $this->membership($user, $group)?->isActive() === true;
    }

    public function post(User $user, Group $group): bool
    {
        return $user->can('posts.create') && ($this->isOwner($user, $group) || $this->membership($user, $group)?->isActive() === true);
    }

    /**
     * Valider les demandes, exclure, épingler : propriétaire, admin ou modérateur·rice du groupe, ou modération globale.
     */
    public function moderate(User $user, Group $group): bool
    {
        return $user->can('moderation.manage') || $this->isOwner($user, $group) || $this->hasRole($user, $group);
    }

    private function isOwner(User $user, Group $group): bool
    {
        return $group->owner_id !== null && (int) $group->owner_id === (int) $user->getKey();
    }

    private function hasRole(User $user, Group $group, bool $admin = false): bool
    {
        $membership = $this->membership($user, $group);

        if ($membership === null || ! $membership->isActive()) {
            return false;
        }

        return $admin ? $membership->role === GroupRole::Admin : $membership->canModerate();
    }

    private function membership(User $user, Group $group): ?GroupMember
    {
        return GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $user->getKey())
            ->first();
    }
}
