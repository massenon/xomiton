<?php

declare(strict_types=1);

namespace App\Application\Community\Groups;

use App\Domain\Community\Exceptions\LastGroupAdmin;
use App\Domain\Community\Exceptions\MemberNotManageable;
use App\Domain\Community\GroupMembershipRules;
use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Models\ConversationParticipant;
use App\Models\DatabaseNotification;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\GroupJoinAccepted;
use App\Notifications\GroupJoinRequested;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adhésions à un groupe (P8-02, groups.php, group-details.php ; règles App\Domain\Community\GroupMembershipRules) :
 *
 *  - rejoindre : groupe public → membre active aussitôt ; groupe privé → demande notifiée à l'équipe du groupe
 *    (propriétaire et admins : GroupJoinRequested, cloche + push, préférence « Communauté ») ; un rejeu ne crée rien de
 *    plus (idempotent, deux envois simultanés retenus par l'index unique `(group_id, user_id)`). Jamais de nouvelle
 *    notification à une personne de l'équipe qui en a une non lue pour ce couple groupe + demandeur·se, ni pendant
 *    `community.join_request_renotify_hours` après la précédente (annuler puis redemander ne harcèle pas) ; jamais
 *    vers un compte inactif, suspendu ou anonymisé, ni entre comptes qui se bloquent (dans un sens ou dans l'autre) ;
 *  - quitter : membre active (dernière admin retenue : 422 `group_last_admin` ; la propriétaire qui part cède la
 *    propriété à l'admin la plus ancienne) ou annuler sa demande ; la personne quitte aussi la discussion du groupe ;
 *  - accepter une demande (relue sous verrou : deux acceptations simultanées n'en font qu'une ; GroupJoinAccepted à la
 *    personne), refuser une demande ou retirer une membre, changer un rôle (reporté sur la discussion du groupe) :
 *    équipe du groupe selon GroupMembershipRules::canManage / canChangeRole (403 `group_member_not_manageable` sinon) ;
 *    une demande annulée, acceptée ou refusée passe ses GroupJoinRequested à « lue ».
 * Journal d'audit : ni rejoindre, ni quitter, ni demander, ni accepter (appartenir à un groupe peut révéler une
 * identité, RG-02). Seuls les gestes de modération le sont, avec l'acteur et la personne visée (`group.member_removed`,
 * `group.role_changed`, `user:<id>`), et la création (`group.created`, acteur = propriétaire) : journal lu par la
 * seule administration (écart assumé, Q75).
 */
final class ManageGroupMembership
{
    public function __construct(
        private readonly GroupCounters $counters,
        private readonly GroupDiscussion $discussion,
    ) {}

    public function join(User $user, Group $group): GroupMember
    {
        $status = GroupMembershipRules::joinStatus($group->privacy);
        $created = false;

        try {
            /** @var GroupMember $membership */
            $membership = DB::transaction(function () use ($user, $group, $status, &$created): GroupMember {
                $existing = $this->membershipQuery($group, $user)->lockForUpdate()->first();

                if ($existing !== null && ($existing->status !== MembershipStatus::Requested || $status === MembershipStatus::Requested)) {
                    return $existing;
                }

                // Demande en attente d'un groupe redevenu public : elle devient une adhésion.
                $membership = $existing ?? new GroupMember(['group_id' => $group->getKey(), 'user_id' => $user->getKey(), 'role' => GroupRole::Member]);
                $membership->forceFill(['status' => $status, 'joined_at' => $status === MembershipStatus::Active ? now() : null])->save();
                $created = true;

                if ($status === MembershipStatus::Active) {
                    $this->counters->increment($group);
                }

                return $membership;
            });
        } catch (QueryException $exception) {
            // Deux adhésions simultanées : l'index unique (group_id, user_id) a gardé la première.
            $membership = $this->membershipQuery($group, $user)->first() ?? throw $exception;
            $created = false;
        }

        if ($created && $membership->status === MembershipStatus::Requested) {
            $this->notifyTeam($group, $user);
        }

        return $membership;
    }

    /**
     * @throws LastGroupAdmin
     */
    public function leave(User $user, Group $group): void
    {
        $cancelled = DB::transaction(function () use ($user, $group): bool {
            $membership = $this->membershipQuery($group, $user)->lockForUpdate()->first();

            if ($membership === null || $membership->status === MembershipStatus::Banned) {
                throw new NotFoundHttpException;
            }

            if ($membership->status === MembershipStatus::Requested) {
                $membership->delete();

                return true;
            }

            $otherAdmins = $this->otherAdmins($group, $user);

            if (! GroupMembershipRules::canLeave($membership->role === GroupRole::Admin, $otherAdmins->count())) {
                throw new LastGroupAdmin;
            }

            if ((int) $group->owner_id === (int) $user->getKey()) {
                $group->forceFill(['owner_id' => $otherAdmins->first()?->user_id])->save();
            }

            $membership->delete();
            $this->counters->decrement($group);

            return false;
        });

        if ($cancelled) {
            $this->settleRequestNotifications($group, $user);

            return;
        }

        $this->discussion->remove($group, $user, ConversationParticipant::LEFT_SELF);

        if ($group->owner_id !== null) {
            $this->discussion->syncRole($group, (int) $group->owner_id);
        }
    }

    public function accept(User $actor, Group $group, User $user): GroupMember
    {
        $this->assertManageable($actor, $group, $this->target($group, $user, MembershipStatus::Requested));

        /** @var GroupMember $membership */
        $membership = DB::transaction(function () use ($group, $user): GroupMember {
            // Relue sous verrou : une seconde acceptation simultanée trouve une membre active (404), sans double compte.
            $membership = $this->membershipQuery($group, $user)->lockForUpdate()->first();

            if ($membership === null || $membership->status !== MembershipStatus::Requested) {
                throw new NotFoundHttpException;
            }

            $membership->forceFill(['status' => MembershipStatus::Active, 'joined_at' => now()])->save();
            $this->counters->increment($group);

            return $membership;
        });

        $this->settleRequestNotifications($group, $user);

        if ($user->isActive()) {
            $user->notify(new GroupJoinAccepted((int) $group->getKey(), (string) $group->name, GroupLinks::show($group)));
        }

        return $membership;
    }

    /**
     * Refuser une demande ou retirer une membre active.
     */
    public function remove(User $actor, Group $group, User $user): void
    {
        $this->assertManageable($actor, $group, $this->target($group, $user));

        $wasActive = DB::transaction(function () use ($group, $user): bool {
            $membership = $this->membershipQuery($group, $user)->lockForUpdate()->first();

            if ($membership === null || ! in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Requested], true)) {
                throw new NotFoundHttpException;
            }

            $wasActive = $membership->isActive();
            $membership->delete();

            if ($wasActive) {
                $this->counters->decrement($group);
            }

            return $wasActive;
        });

        if (! $wasActive) {
            $this->settleRequestNotifications($group, $user);

            return;
        }

        $this->discussion->remove($group, $user, ConversationParticipant::LEFT_REMOVED);
        Audit::log('group.member_removed', $group, 'user:'.$user->getKey(), $actor);
    }

    public function changeRole(User $actor, Group $group, User $user, GroupRole $role): GroupMember
    {
        $membership = $this->target($group, $user, MembershipStatus::Active);
        $actorMembership = $this->actorMembership($actor, $group);
        $actorIsOwner = (int) $group->owner_id === (int) $actor->getKey() || $actor->can('moderation.manage');
        $targetIsOwner = (int) $group->owner_id === (int) $user->getKey();

        if (! GroupMembershipRules::canChangeRole($actorIsOwner, $actorMembership?->role, $targetIsOwner, $membership->role)
            || ($role === GroupRole::Admin && ! $actorIsOwner)) {
            throw new MemberNotManageable;
        }

        if ($membership->role !== $role) {
            $membership->forceFill(['role' => $role])->save();
            $this->discussion->syncRole($group, (int) $user->getKey());
            Audit::log('group.role_changed', $group, 'user:'.$user->getKey().' → '.$role->value, $actor);
        }

        return $membership;
    }

    /**
     * Propriétaire et admins actives, comptes actifs, sans blocage avec la personne qui demande ; sans celles qui ont
     * déjà une notification non lue pour cette demande ou en ont reçu une dans le délai de relance.
     */
    private function notifyTeam(Group $group, User $requester): void
    {
        $requesterId = $requester->getKey();
        $since = now()->subHours(max(0, (int) config('xomiton.community.join_request_renotify_hours', 24)));

        $team = User::query()
            ->active()
            ->whereKeyNot($requesterId)
            ->where(static fn (Builder $q) => $q->whereKey($group->owner_id ?? 0)
                ->orWhereIn('users.id', GroupMember::query()->select('user_id')->where('group_id', $group->getKey())
                    ->where('status', MembershipStatus::Active)->where('role', GroupRole::Admin)))
            ->whereNotIn('users.id', UserBlock::query()->select('blocked_user_id')->where('user_id', $requesterId))
            ->whereNotIn('users.id', UserBlock::query()->select('user_id')->where('blocked_user_id', $requesterId))
            ->whereNotIn('users.id', $this->requestNotifications($group, $requester)
                ->where(static fn (Builder $q) => $q->whereNull('read_at')->orWhere('created_at', '>=', $since))
                ->select('notifiable_id'))
            ->get();

        if ($team->isEmpty()) {
            return;
        }

        Notification::send($team, new GroupJoinRequested(
            (int) $group->getKey(),
            (int) $requesterId,
            (string) $requester->pseudo,
            (string) $group->name,
            GroupLinks::show($group, 'members'),
        ));
    }

    /** Demande tranchée ou annulée : ses notifications ne restent pas « non lues » dans la cloche de l'équipe. */
    private function settleRequestNotifications(Group $group, User $requester): void
    {
        $this->requestNotifications($group, $requester)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function requestNotifications(Group $group, User $requester): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $requester->getMorphClass())
            ->where('type', GroupJoinRequested::class)
            ->where('data->group_id', (int) $group->getKey())
            ->where('data->requester_id', (int) $requester->getKey());
    }

    /**
     * @return Builder<GroupMember>
     */
    private function membershipQuery(Group $group, User $user): Builder
    {
        return GroupMember::query()->where('group_id', $group->getKey())->where('user_id', $user->getKey());
    }

    /**
     * Admins actives du groupe autres que la personne, de la plus ancienne à la plus récente.
     *
     * @return Collection<int, GroupMember>
     */
    private function otherAdmins(Group $group, User $user): Collection
    {
        return GroupMember::query()->where('group_id', $group->getKey())
            ->where('user_id', '!=', $user->getKey())
            ->where('status', MembershipStatus::Active)->where('role', GroupRole::Admin)
            ->orderBy('joined_at')->orderBy('id')
            ->get();
    }

    private function target(Group $group, User $user, ?MembershipStatus $status = null): GroupMember
    {
        $membership = $this->membershipQuery($group, $user)
            ->when($status !== null, static fn ($q) => $q->where('status', $status))
            ->when($status === null, static fn ($q) => $q->whereIn('status', [MembershipStatus::Active, MembershipStatus::Requested]))
            ->first();

        if ($membership === null) {
            throw new NotFoundHttpException;
        }

        return $membership;
    }

    private function actorMembership(User $actor, Group $group): ?GroupMember
    {
        $membership = $this->membershipQuery($group, $actor)->first();

        return $membership?->isActive() ? $membership : null;
    }

    /**
     * @throws MemberNotManageable
     */
    private function assertManageable(User $actor, Group $group, GroupMember $target): void
    {
        $actorIsOwner = (int) $group->owner_id === (int) $actor->getKey() || $actor->can('moderation.manage');
        $targetIsOwner = (int) $group->owner_id === (int) $target->user_id;

        if (! GroupMembershipRules::canManage($actorIsOwner, $this->actorMembership($actor, $group)?->role, $targetIsOwner, $target->role)) {
            throw new MemberNotManageable;
        }
    }
}
