<?php

declare(strict_types=1);

namespace App\Application\Community\Network;

use App\Application\Community\Events\EventAccess;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Onglets « Invitations reçues » et « Invitations envoyées » de /qui-suivre et leurs compteurs (P8-06,
 * who-to-follow.php l.54-55) : invitations **en attente** dont l'autre personne est active, non anonymisée et sans
 * blocage avec celle qui lit, dans un sens ou dans l'autre (une invitation entre comptes qui se bloquent n'existe plus
 * pour l'un ni pour l'autre : ni dans la liste, ni dans le compteur, ni pour un geste — 404). Plus récentes d'abord ;
 * pagination par curseur (NetworkPage : accepter, refuser ou annuler sur la page 1 ne fait sauter aucune invitation de la
 * page 2).
 */
final class InvitationDirectory
{
    public const RECEIVED = 'received';

    public const SENT = 'sent';

    public const BOXES = [self::RECEIVED, self::SENT];

    /**
     * @return Builder<Invitation>
     */
    public function query(User $viewer, string $box): Builder
    {
        $id = (int) $viewer->getKey();
        $received = $box === self::RECEIVED;
        $mine = $received ? 'to_user_id' : 'from_user_id';
        $other = $received ? 'from_user_id' : 'to_user_id';
        $relation = $received ? 'fromUser' : 'toUser';

        $query = Invitation::query()
            ->pending()
            ->where('invitations.'.$mine, $id)
            ->whereHas($relation, static fn (Builder $user) => $user->active()->notAnonymized())
            ->with([$relation.'.profile', $relation.'.roles', $relation.'.zone']);

        return EventAccess::withoutBlocksWith($query, 'invitations.'.$other, $id)
            ->orderByDesc('invitations.created_at')
            ->orderByDesc('invitations.id');
    }

    /**
     * Une page d'invitations, après le curseur reçu (`meta.next_cursor` de la page précédente).
     *
     * @return NetworkPage<Invitation>
     */
    public function page(User $viewer, string $box, int $perPage, ?string $cursor = null): NetworkPage
    {
        $query = $this->query($viewer, $box);
        $after = NetworkPage::after($cursor, ['c', 'i']);

        if ($after !== null) {
            $c = (string) $after['c'];
            $i = (int) $after['i'];
            $query->where(static fn (Builder $q) => $q->where('invitations.created_at', '<', $c)
                ->orWhere(static fn (Builder $tie) => $tie->where('invitations.created_at', $c)->where('invitations.id', '<', $i)));
        }

        return NetworkPage::fetch($query, $perPage, static fn (Invitation $invitation): array => [
            'c' => (string) $invitation->getRawOriginal('created_at'),
            'i' => (int) $invitation->getKey(),
        ]);
    }

    /**
     * @return array{received: int, sent: int}
     */
    public function counts(User $viewer): array
    {
        return [
            'received' => $this->query($viewer, self::RECEIVED)->count(),
            'sent' => $this->query($viewer, self::SENT)->count(),
        ];
    }
}
