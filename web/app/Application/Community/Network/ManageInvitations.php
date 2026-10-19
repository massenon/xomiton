<?php

declare(strict_types=1);

namespace App\Application\Community\Network;

use App\Domain\Community\Exceptions\AlreadyConnected;
use App\Domain\Community\Exceptions\InvitationFromThem;
use App\Domain\Community\Exceptions\InvitationNotPending;
use App\Domain\Community\Exceptions\InvitationUnavailable;
use App\Domain\Community\NetworkRules;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Inviter, accepter, refuser, annuler (P8-06, who-to-follow.php l.14-46) :
 *
 *  - **inviter** : compte invitable (NetworkPeople::canInvite) **et** trouvable (NetworkPeople::discoverableBy :
 *    `searchable`, jamais superadmin) ou déjà lié (NetworkPeople::linked : abonnement ou invitation en attente, dans un
 *    sens ou dans l'autre) — sinon 422 `invitation_unavailable`, même réponse pour un compte inconnu, bloqué, privé ou
 *    « non trouvable » (P8-09 : un identifiant ne contourne pas le réglage) ; pas déjà connecté (`already_connected`), pas
 *    déjà invité·e par l'autre
 *    (`invitation_from_them`). **Sans doublon** (unique `(from_user_id, to_user_id)`) : une invitation en attente est
 *    rendue telle quelle (rejeu, double clic, deux envois simultanés) ; une invitation acceptée dont la connexion a été
 *    défaite, ou refusée depuis plus de `network.reinvite_after_refusal_days`, est rouverte sur la même ligne ; refusée
 *    plus récemment : `invitation_unavailable` (le refus n'est pas nommé) ;
 *  - **accepter** (personne invitée) : statut `acceptee`, `responded_at`, abonnement dans les deux sens (« Vous êtes
 *    connecté·es »), invitation inverse en attente close de même (course), InvitationAccepted à la personne qui avait
 *    invité ;
 *  - **refuser** (personne invitée) : statut `refusee`, `responded_at`, aucune notification ;
 *  - **annuler** (personne qui a invité) : ligne supprimée comme dans le prototype (une nouvelle invitation reste
 *    possible aussitôt) ; seulement en attente (sinon `false` : l'API répond 404, comme un blocage, Q79).
 * Répéter la même réponse est sans effet (200) ; répondre autrement à une invitation tranchée : 422
 * `invitation_not_pending`. La cloche de la personne invitée est soldée à chaque issue (InvitationNotifier::settle), y
 * compris pour l'invitation inverse close par une acceptation.
 * Aucun journal d'audit : savoir qui se connecte à qui peut révéler une identité (RG-02), comme les adhésions (Q75).
 */
final class ManageInvitations
{
    public const READY = 'ready';

    public const PENDING = 'pending';

    public const CONNECTED = 'connected';

    public const FROM_THEM = 'from_them';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        private readonly NetworkPeople $people,
        private readonly InvitationNotifier $notifier,
    ) {}

    /**
     * @return array{invitation: Invitation, created: bool}
     */
    public function send(User $sender, int $targetId): array
    {
        $target = User::query()->with('profile')->find($targetId);
        ['status' => $status, 'existing' => $existing] = $this->assess($sender, $target);

        match ($status) {
            self::UNAVAILABLE => throw new InvitationUnavailable,
            self::CONNECTED => throw new AlreadyConnected,
            self::FROM_THEM => throw new InvitationFromThem,
            default => null,
        };

        if ($status === self::PENDING && $existing !== null) {
            return ['invitation' => $existing, 'created' => false];
        }

        /** @var User $target */
        [$invitation, $created] = $existing !== null ? $this->reopen($existing) : $this->create($sender, $target);

        if ($created) {
            $this->notifier->invited($invitation, $sender, $target);
        }

        return ['invitation' => $invitation, 'created' => $created];
    }

    /**
     * Ce que donnerait une invitation, sans rien écrire (`/qui-suivre?invite=<id>`, P8-06) : `ready` (avec la personne),
     * `pending` (déjà invitée), `connected`, `from_them`, ou `unavailable` (sans la personne : rien n'est révélé, ni
     * pour un compte « non trouvable » ou superadmin qu'aucun lien ne relie à la personne qui lit) ; `following` : la
     * personne qui lit la suit déjà.
     *
     * @return array{status: string, target: User|null, following: bool}
     */
    public function preview(User $sender, int $targetId): array
    {
        $target = User::query()->with(['profile', 'roles', 'zone'])->find($targetId);
        $status = $this->assess($sender, $target)['status'];

        if ($status === self::UNAVAILABLE || ! $target instanceof User) {
            return ['status' => self::UNAVAILABLE, 'target' => null, 'following' => false];
        }

        return ['status' => $status, 'target' => $target, 'following' => $this->people->follows($sender, $target)];
    }

    public function accept(User $recipient, Invitation $invitation): Invitation
    {
        /** @var array{accepted: bool, inverse: list<Invitation>} $outcome */
        $outcome = DB::transaction(function () use ($recipient, $invitation): array {
            $fresh = Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->status === InvitationStatus::Accepted) {
                return ['accepted' => false, 'inverse' => []];
            }

            if (! $fresh->isPending()) {
                throw new InvitationNotPending;
            }

            $now = now();
            $fresh->forceFill(['status' => InvitationStatus::Accepted, 'responded_at' => $now])->save();
            // Course : l'autre personne avait été invitée en même temps ; son invitation inverse se clôt de même.
            $inverse = Invitation::query()->pending()->where('from_user_id', $recipient->getKey())->where('to_user_id', $fresh->from_user_id)
                ->lockForUpdate()->get()->all();

            foreach ($inverse as $other) {
                $other->forceFill(['status' => InvitationStatus::Accepted, 'responded_at' => $now])->save();
            }

            DB::table('follows')->insertOrIgnore([
                ['follower_id' => (int) $fresh->from_user_id, 'followed_id' => (int) $recipient->getKey(), 'created_at' => $now],
                ['follower_id' => (int) $recipient->getKey(), 'followed_id' => (int) $fresh->from_user_id, 'created_at' => $now],
            ]);

            return ['accepted' => true, 'inverse' => $inverse];
        });
        $accepted = $outcome['accepted'];

        $invitation->refresh();
        $this->notifier->settle($invitation);

        foreach ($outcome['inverse'] as $other) {
            $this->notifier->settle($other);
        }

        if ($accepted) {
            $this->notifier->accepted($invitation, $recipient);
        }

        return $invitation;
    }

    public function refuse(User $recipient, Invitation $invitation): Invitation
    {
        DB::transaction(static function () use ($invitation): void {
            $fresh = Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->status === InvitationStatus::Refused) {
                return;
            }

            if (! $fresh->isPending()) {
                throw new InvitationNotPending;
            }

            $fresh->forceFill(['status' => InvitationStatus::Refused, 'responded_at' => now()])->save();
        });

        $invitation->refresh();
        $this->notifier->settle($invitation);

        return $invitation;
    }

    /**
     * @return bool false si l'invitation n'est plus en attente (acceptée, refusée, annulée entre-temps) : rien n'est fait
     */
    public function cancel(User $sender, Invitation $invitation): bool
    {
        if (! $invitation->isPending()) {
            return false;
        }

        $deleted = Invitation::query()->whereKey($invitation->getKey())->pending()->where('from_user_id', $sender->getKey())->delete();

        if ($deleted === 0) {
            return false;
        }

        $this->notifier->settle($invitation);

        return true;
    }

    /**
     * @return array{status: string, existing: Invitation|null}
     */
    private function assess(User $sender, ?User $target): array
    {
        if (! $target instanceof User || ! $this->people->canInvite($sender, $target)
            || (! $this->people->discoverable($sender, $target) && ! $this->people->linked($sender, $target))) {
            return ['status' => self::UNAVAILABLE, 'existing' => null];
        }

        if ($this->people->connected($sender, $target)) {
            return ['status' => self::CONNECTED, 'existing' => null];
        }

        $existing = $this->between($sender, $target);

        if ($existing?->isPending()) {
            return ['status' => self::PENDING, 'existing' => $existing];
        }

        if ($this->between($target, $sender)?->isPending()) {
            return ['status' => self::FROM_THEM, 'existing' => null];
        }

        if ($existing !== null && NetworkRules::refusalStillHolds($existing->status, $existing->responded_at, now(), (int) config('xomiton.network.reinvite_after_refusal_days', 30))) {
            return ['status' => self::UNAVAILABLE, 'existing' => null];
        }

        return ['status' => self::READY, 'existing' => $existing];
    }

    private function between(User $from, User $to): ?Invitation
    {
        return Invitation::query()->where('from_user_id', $from->getKey())->where('to_user_id', $to->getKey())->first();
    }

    /**
     * @return array{0: Invitation, 1: bool}
     */
    private function reopen(Invitation $invitation): array
    {
        $now = now();
        $reopened = Invitation::query()->whereKey($invitation->getKey())->where('status', '!=', InvitationStatus::Pending)
            ->update(['status' => InvitationStatus::Pending->value, 'responded_at' => null, 'created_at' => $now, 'updated_at' => $now]);

        return [$invitation->refresh(), $reopened > 0];
    }

    /**
     * @return array{0: Invitation, 1: bool}
     */
    private function create(User $sender, User $target): array
    {
        try {
            $invitation = Invitation::query()->create([
                'from_user_id' => $sender->getKey(),
                'to_user_id' => $target->getKey(),
                'status' => InvitationStatus::Pending,
            ]);

            return [$invitation, true];
        } catch (QueryException) {
            // Deux envois simultanés : la contrainte unique a gardé le premier.
            return [Invitation::query()->where('from_user_id', $sender->getKey())->where('to_user_id', $target->getKey())->firstOrFail(), false];
        }
    }
}
