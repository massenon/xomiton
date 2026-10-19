<?php

declare(strict_types=1);

namespace App\Application\Community\Network;

use App\Models\DatabaseNotification;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationAccepted;
use App\Notifications\InvitationReceived;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloche et push des invitations (P8-06 ; catalogue P3-02, préférence « Communauté ») :
 *
 *  - InvitationReceived à la personne invitée — jamais vers un compte inactif, sans accès au réseau ou qui bloque (ou
 *    est bloqué par) la personne qui invite ; jamais de nouvelle notification si la même personne lui en a envoyé une
 *    encore non lue ou depuis moins de `network.invite_renotify_hours` (inviter, annuler puis réinviter ne harcèle pas) ;
 *  - InvitationAccepted à la personne qui avait invité, mêmes gardes ; un refus n'est **jamais** notifié ;
 *  - une invitation acceptée, refusée ou annulée passe ses InvitationReceived à « lue » (elle ne reste pas en attente
 *    dans la cloche).
 * Nom affiché : le pseudonyme (RG-02). Un échec d'envoi ne fait jamais échouer le geste (journalisé).
 */
final class InvitationNotifier
{
    public function __construct(private readonly NetworkPeople $people) {}

    public function invited(Invitation $invitation, User $sender, User $recipient): void
    {
        if (! $this->deliverable($recipient, $sender) || $this->recentlyNotified($recipient, $sender)) {
            return;
        }

        try {
            $recipient->notify(new InvitationReceived(
                invitationId: (int) $invitation->getKey(),
                fromName: (string) $sender->pseudo,
                url: NetworkLinks::index('received'),
                fromUserId: (int) $sender->getKey(),
            ));
        } catch (Throwable $exception) {
            Log::warning('Réseau : notification d’invitation non envoyée.', ['invitation_id' => $invitation->getKey(), 'error' => $exception->getMessage()]);
        }
    }

    public function accepted(Invitation $invitation, User $acceptor): void
    {
        $sender = $invitation->fromUser;

        if ($sender === null || ! $this->deliverable($sender, $acceptor)) {
            return;
        }

        try {
            $sender->notify(new InvitationAccepted(
                invitationId: (int) $invitation->getKey(),
                acceptedById: (int) $acceptor->getKey(),
                name: (string) $acceptor->pseudo,
                url: NetworkLinks::index(),
            ));
        } catch (Throwable $exception) {
            Log::warning('Réseau : notification d’acceptation non envoyée.', ['invitation_id' => $invitation->getKey(), 'error' => $exception->getMessage()]);
        }
    }

    /** Invitation tranchée ou annulée : ses notifications ne restent pas « non lues » chez la personne invitée. */
    public function settle(Invitation $invitation): void
    {
        DatabaseNotification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('notifiable_id', $invitation->to_user_id)
            ->where('type', InvitationReceived::class)
            ->whereNull('read_at')
            ->where('data->invitation_id', (int) $invitation->getKey())
            ->update(['read_at' => now()]);
    }

    private function deliverable(User $recipient, User $actor): bool
    {
        return $this->people->reachable($recipient) && ! $this->people->blockedBetween($recipient, $actor);
    }

    private function recentlyNotified(User $recipient, User $sender): bool
    {
        $since = now()->subHours(max(0, (int) config('xomiton.network.invite_renotify_hours', 24)));

        return DatabaseNotification::query()
            ->where('notifiable_type', $recipient->getMorphClass())
            ->where('notifiable_id', $recipient->getKey())
            ->where('type', InvitationReceived::class)
            ->where('data->user_id', (int) $sender->getKey())
            ->where(static fn ($q) => $q->whereNull('read_at')->orWhere('created_at', '>=', $since))
            ->exists();
    }
}
