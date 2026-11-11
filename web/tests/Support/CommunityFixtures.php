<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Données de test des groupes et des pages (P8-02, P8-03). Suppose RolesAndPermissionsSeeder exécuté.
 */
final class CommunityFixtures
{
    /**
     * Groupe créé par l'API au nom de `$owner` (propriétaire et admin).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function group(User $owner, array $attributes = []): Group
    {
        $id = FeedFixtures::as($owner)->postJson('/api/v1/groups', $attributes + [
            'name' => 'Cercle d’entraide de Bohicon',
            'description' => 'Entraide et écoute entre pair·es.',
            'privacy' => 'public',
        ])->assertCreated()->json('data.id');

        return Group::query()->where('ulid', $id)->firstOrFail();
    }

    /**
     * Adhésion écrite directement (compteur `members_count` tenu comme le ferait l'action).
     */
    public static function addMember(Group $group, User $user, GroupRole $role = GroupRole::Member, MembershipStatus $status = MembershipStatus::Active): GroupMember
    {
        if ($status === MembershipStatus::Active) {
            Group::query()->whereKey($group->id)->increment('members_count');
        }

        return GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'joined_at' => $status === MembershipStatus::Active ? now() : null,
        ]);
    }

    public static function join(User $user, Group $group): TestResponse
    {
        return FeedFixtures::as($user)->postJson('/api/v1/groups/'.$group->ulid.'/join');
    }

    public static function leave(User $user, Group $group): TestResponse
    {
        return FeedFixtures::as($user)->postJson('/api/v1/groups/'.$group->ulid.'/leave');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function postIn(User $author, Group $group, string $body = 'Bonjour le cercle', array $extra = []): TestResponse
    {
        return FeedFixtures::as($author)->postJson('/api/v1/posts', ['body' => $body, 'group' => $group->ulid] + $extra);
    }
}
