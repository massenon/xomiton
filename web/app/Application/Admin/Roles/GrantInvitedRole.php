<?php

declare(strict_types=1);

namespace App\Application\Admin\Roles;

use App\Application\Admin\Users\ChangeUserRole;
use App\Enums\UserStatus;
use App\Models\RoleInvitation;
use App\Models\User;
use App\Notifications\RoleNotice;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Donne le rôle d'une invitation à l'équipe une fois l'inscription **validée** (décisions client du 2026-10-29) : compte
 * actif et e-mail confirmé (code `signup`), ou compte approuvé par l'administration quand la validation manuelle est
 * active (ReviewRegistration). La personne reste membre ; le rôle `pair` s'ajoute (ManagePeerRole), un rôle de
 * modération ou d'administration aussi — à condition que la personne qui a invité détienne encore `roles.assign_admin`
 * (sinon l'invitation est refusée, jamais d'escalade par une invitation d'un compte déchu). Idempotent.
 */
final class GrantInvitedRole
{
    public function __construct(private readonly ManagePeerRole $peers) {}

    /** Rôle donné, ou null quand le compte n'a pas d'invitation à honorer (ou pas encore validé). */
    public function __invoke(User $user): ?string
    {
        if ($user->status !== UserStatus::Active || $user->email_verified_at === null) {
            return null;
        }

        $invitation = RoleInvitation::query()->where('user_id', $user->getKey())->waitingValidation()->latest('id')->first();

        if ($invitation === null) {
            return null;
        }

        $inviter = $invitation->inviter;

        if ($invitation->role === 'pair') {
            if ($inviter === null || ! $inviter->can('peers.invite')) {
                return $this->refuse($invitation, 'inviter_lost_rights');
            }

            $this->peers->apply($user, $inviter, 'invitation');
        } else {
            if ($inviter === null || ! $inviter->can('roles.assign_admin')) {
                return $this->refuse($invitation, 'inviter_lost_rights');
            }

            DB::transaction(function () use ($user, $invitation): void {
                $user->syncRoles(array_map(
                    static fn (string $name): Role => Role::findOrCreate($name, RolesAndPermissionsSeeder::GUARD),
                    ChangeUserRole::rolesFor($user, $invitation->role),
                ));
            });

            Audit::log('roles.admin_granted', $user, sprintf('pseudo=%s ; role=%s ; via=invitation', $user->pseudo, $invitation->role), $inviter);
            $user->notify(new RoleNotice(RoleNotice::ADMIN_GRANTED));
        }

        $invitation->forceFill([
            'status' => RoleInvitation::STATUS_ACCEPTED,
            'decided_at' => now(),
            'decided_by' => $inviter->getKey(),
        ])->save();

        Audit::log('invitations.accepted', $invitation, sprintf('role=%s ; code=…%s', $invitation->role, $invitation->code_hint), $user);

        return $invitation->role;
    }

    private function refuse(RoleInvitation $invitation, string $reason): null
    {
        $invitation->forceFill(['status' => RoleInvitation::STATUS_REFUSED, 'decided_at' => now(), 'decision_reason' => $reason])->save();
        Audit::logWithoutRequest('invitations.refused', $invitation, 'role='.$invitation->role.' ; motif='.$reason, 'système');

        return null;
    }
}
