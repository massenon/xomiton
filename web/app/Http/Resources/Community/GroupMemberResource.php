<?php

declare(strict_types=1);

namespace App\Http\Resources\Community;

use App\Domain\Community\GroupMembershipRules;
use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Membre d'un groupe telle qu'elle sort de l'API (P8-02, docs/API.md §13.3) : identifiant du compte, pseudonyme et
 * avatar (jamais le nom réel ni l'e-mail, RG-02), rôle dans le groupe, statut, date d'arrivée, et ce que la personne
 * qui lit peut faire (`can` : accepter, retirer, changer le rôle) selon App\Domain\Community\GroupMembershipRules.
 */
final class GroupMemberResource
{
    private function __construct() {}

    /**
     * @param  iterable<GroupMember>  $members
     * @return list<array<string, mixed>>
     */
    public static function list(iterable $members, Group $group, User $viewer, ?GroupMember $viewerMembership): array
    {
        $global = $viewer->can('moderation.manage');
        $actorIsOwner = (int) ($group->owner_id ?? 0) === (int) $viewer->getKey() || $global;
        $actorRole = $viewerMembership?->status === MembershipStatus::Active ? $viewerMembership->role : null;

        return Collection::make($members)
            ->map(static function (GroupMember $member) use ($group, $viewer, $actorIsOwner, $actorRole): array {
                $user = $member->user;
                $isOwner = (int) ($group->owner_id ?? 0) === (int) $member->user_id;
                $isMe = (int) $member->user_id === (int) $viewer->getKey();
                $manageable = ! $isMe && GroupMembershipRules::canManage($actorIsOwner, $actorRole, $isOwner, $member->role);
                $pending = $member->status === MembershipStatus::Requested;

                return [
                    'id' => (int) $member->user_id,
                    'pseudo' => (string) ($user?->pseudo ?? __('feed.post.default_author')),
                    'avatar_url' => $user?->avatar_url,
                    'role' => $member->role->value,
                    'role_label' => $isOwner ? (string) __('community.groups.detail.owner') : $member->role->label(),
                    'status' => $member->status->value,
                    'is_owner' => $isOwner,
                    'is_me' => $isMe,
                    'joined_at' => $member->joined_at?->toIso8601String(),
                    'requested_at' => $pending ? $member->created_at?->toIso8601String() : null,
                    'can' => [
                        'accept' => $pending && $manageable,
                        'remove' => $manageable,
                        'change_role' => ! $pending && ! $isMe && GroupMembershipRules::canChangeRole($actorIsOwner, $actorRole, $isOwner, $member->role),
                        'make_admin' => ! $pending && ! $isMe && ! $isOwner && $actorIsOwner && $member->role !== GroupRole::Admin,
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
