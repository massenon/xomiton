<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Community\Network\NetworkPeople;
use App\Models\Invitation;
use App\Models\User;

/**
 * Invitations à se connecter (P8-06, who-to-follow.php) : `social.access` liste et invite (e-mail confirmé exigé par la
 * route, `verified`) ; une invitation n'existe que pour ses deux parties, tant que l'autre compte est actif, non
 * anonymisé et qu'aucune des deux ne bloque l'autre (`view` faux → 404) ; la personne invitée accepte ou refuse, celle
 * qui a invité annule.
 */
final class InvitationPolicy
{
    public function __construct(private readonly NetworkPeople $people) {}

    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function create(User $user): bool
    {
        return $user->can('social.access');
    }

    public function view(User $user, Invitation $invitation): bool
    {
        if (! $user->can('social.access')) {
            return false;
        }

        $id = (int) $user->getKey();
        $isRecipient = (int) $invitation->to_user_id === $id;

        if (! $isRecipient && (int) $invitation->from_user_id !== $id) {
            return false;
        }

        $other = $isRecipient ? $invitation->fromUser : $invitation->toUser;

        return $other !== null && $other->isActive() && ! $other->isAnonymized() && ! $this->people->blockedBetween($user, $other);
    }

    /** Accepter ou refuser : la personne invitée. */
    public function respond(User $user, Invitation $invitation): bool
    {
        return (int) $invitation->to_user_id === (int) $user->getKey() && $this->view($user, $invitation);
    }

    /** Annuler une invitation envoyée : la personne qui a invité. */
    public function cancel(User $user, Invitation $invitation): bool
    {
        return (int) $invitation->from_user_id === (int) $user->getKey() && $this->view($user, $invitation);
    }
}
