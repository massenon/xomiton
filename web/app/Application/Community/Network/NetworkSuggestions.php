<?php

declare(strict_types=1);

namespace App\Application\Community\Network;

use App\Application\Community\Data\NetworkFilters;
use App\Domain\Community\NetworkRules;
use App\Enums\InvitationStatus;
use App\Models\Follow;
use App\Models\Invitation;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Onglet « Suggestions » de /qui-suivre et carte « Qui suivre » du fil (P8-06, who-to-follow.php l.57-64,
 * inc/render.php x_render_who_to_follow) : comptes découvrables (NetworkPeople::discoverableBy) que la personne ne suit
 * pas encore, sans invitation en attente entre les deux (dans un sens ou dans l'autre), ni invitation de sa part
 * refusée il y a moins de `network.reinvite_after_refusal_days`. Filtres : pseudonyme (contient, jamais le nom réel),
 * zone (profils qui l'affichent seulement : filtrer ne révèle pas une zone masquée), rôle public. Ordre : même zone que
 * la personne d'abord (zones affichées, `network_rank`), puis pseudonyme ; pagination par curseur sur ces clés
 * (NetworkPage : inviter ou suivre une personne de la page 1 ne fait sauter personne de la page 2).
 */
final class NetworkSuggestions
{
    public function __construct(private readonly NetworkPeople $people) {}

    /**
     * @return Builder<User>
     */
    public function query(User $viewer, NetworkFilters $filters): Builder
    {
        $id = (int) $viewer->getKey();
        $cutoff = now()->subDays(max(0, (int) config('xomiton.network.reinvite_after_refusal_days', 30)));

        $query = $this->people->discoverableBy($viewer)
            ->with(['profile', 'roles', 'zone'])
            ->withExists(['followers as followed_by_viewer' => static fn (Builder $q) => $q->where('follows.follower_id', $id)])
            ->whereNotIn('users.id', Follow::query()->select('followed_id')->where('follower_id', $id))
            ->whereNotIn('users.id', Invitation::query()->select('to_user_id')->where('from_user_id', $id)
                ->where(static fn (Builder $q) => $q->where('status', InvitationStatus::Pending)
                    ->orWhere(static fn (Builder $r) => $r->where('status', InvitationStatus::Refused)
                        ->where(static fn (Builder $s) => $s->whereNull('responded_at')->orWhere('responded_at', '>', $cutoff)))))
            ->whereNotIn('users.id', Invitation::query()->select('from_user_id')->where('to_user_id', $id)->where('status', InvitationStatus::Pending));

        if ($filters->q !== null && $filters->q !== '') {
            $query->whereRaw(LikePattern::clause('users.pseudo'), [LikePattern::contains($filters->q)]);
        }

        if ($filters->zoneId !== null) {
            $query->where('users.zone_id', $filters->zoneId)
                ->where(static fn (Builder $q) => $q->whereNull('profiles.id')->orWhere('profiles.show_zone', true));
        }

        match ($filters->role) {
            NetworkRules::ROLE_PEER => $query->role(NetworkRules::ROLE_NAMES[NetworkRules::ROLE_PEER]),
            NetworkRules::ROLE_TEAM => $query->role(array_values(array_diff(NetworkRules::ROLE_NAMES[NetworkRules::ROLE_TEAM], NetworkRules::HIDDEN_ROLES))),
            NetworkRules::ROLE_MEMBER => $query->withoutRole([...NetworkRules::ROLE_NAMES[NetworkRules::ROLE_PEER], ...NetworkRules::ROLE_NAMES[NetworkRules::ROLE_TEAM]]),
            default => null,
        };

        [$rank, $bindings] = self::rank($viewer);

        return $query->selectRaw($rank.' as network_rank', $bindings)
            ->orderBy('network_rank')->orderBy('users.pseudo')->orderBy('users.id');
    }

    /**
     * Une page de suggestions, après le curseur reçu (`meta.next_cursor` de la page précédente).
     *
     * @return NetworkPage<User>
     */
    public function page(User $viewer, NetworkFilters $filters, int $perPage, ?string $cursor = null): NetworkPage
    {
        $query = $this->query($viewer, $filters);
        $after = NetworkPage::after($cursor, ['r', 'p', 'i']);

        if ($after !== null) {
            [$rank, $bindings] = self::rank($viewer);
            $r = (int) $after['r'];
            $p = (string) $after['p'];
            $i = (int) $after['i'];

            $query->where(static fn (Builder $q) => $q->whereRaw($rank.' > ?', [...$bindings, $r])
                ->orWhere(static fn (Builder $same) => $same->whereRaw($rank.' = ?', [...$bindings, $r])
                    ->where(static fn (Builder $next) => $next->where('users.pseudo', '>', $p)
                        ->orWhere(static fn (Builder $tie) => $tie->where('users.pseudo', $p)->where('users.id', '>', $i)))));
        }

        return NetworkPage::fetch($query, $perPage, static fn (User $user): array => [
            'r' => (int) $user->getAttribute('network_rank'),
            'p' => (string) $user->pseudo,
            'i' => (int) $user->getKey(),
        ]);
    }

    /**
     * Carte « Qui suivre » de la colonne droite du fil : les premières suggestions, sans filtre.
     *
     * @return Collection<int, User>
     */
    public function forCard(User $viewer, int $limit): Collection
    {
        return $this->query($viewer, NetworkFilters::none())->limit(max(1, $limit))->get();
    }

    /**
     * Rang de tri « même zone d'abord » (0 : même zone, affichée ; 1 : autre) et ses liaisons.
     *
     * @return array{0: string, 1: list<int>}
     */
    private static function rank(User $viewer): array
    {
        if ($viewer->zone_id === null) {
            return ['1', []];
        }

        return ['(case when users.zone_id = ? and coalesce(profiles.show_zone, 1) = 1 then 0 else 1 end)', [(int) $viewer->zone_id]];
    }
}
