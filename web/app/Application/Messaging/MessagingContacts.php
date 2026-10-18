<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Domain\Account\ProfileAudience;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\Follow;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Qui peut écrire à qui (P8-01) : ouvrir une conversation ou ajouter quelqu'un à un groupe exige que la personne soit
 * **joignable** (ni suspendue, ni anonymisée), membre du réseau (`social.access`), sans blocage dans un sens ou dans
 * l'autre (FR-SOC-04) et que son profil soit visible (App\Domain\Account\ProfileAudience : `private` → personne,
 * `followers` → ses abonné·es). Une conversation déjà ouverte continue de suivre ConversationRules::canSend.
 *
 * Recherche de destinataires (« Nouvelle conversation ») : préfixe du pseudonyme (jamais le nom réel, RG-02), comptes
 * `searchable` ou que la personne suit ; sans saisie, ses connexions (abonnements et abonné·es).
 */
final class MessagingContacts
{
    public function reachable(User $user): bool
    {
        return $user->status !== UserStatus::Suspended && ! $user->isAnonymized();
    }

    public function blockedBetween(User|int $a, User|int $b): bool
    {
        return UserBlock::query()->between(self::id($a), self::id($b))->exists();
    }

    /**
     * Entrée dans un groupe : vrai si l'une des personnes qui entrent a un blocage, dans un sens ou dans l'autre, avec
     * une personne déjà présente ou qui entre en même temps (personne n'est mis·e dans un groupe avec qui l'a bloqué·e
     * ou qu'il ou elle a bloqué·e).
     *
     * @param  list<int>  $entering
     * @param  list<int>  $present
     */
    public function blockAmong(array $entering, array $present): bool
    {
        $everyone = array_values(array_unique([...$entering, ...$present]));

        if ($entering === [] || count($everyone) < 2) {
            return false;
        }

        return UserBlock::query()
            ->where(fn (Builder $q) => $q->whereIn('user_id', $entering)->whereIn('blocked_user_id', $everyone))
            ->orWhere(fn (Builder $q) => $q->whereIn('blocked_user_id', $entering)->whereIn('user_id', $everyone))
            ->exists();
    }

    /**
     * Comptes qui ont bloqué la personne (dans un groupe, leurs messages lui sont masqués aussi).
     *
     * @return list<int>
     */
    public function blockersOf(User $viewer): array
    {
        return array_map('intval', UserBlock::query()->where('blocked_user_id', $viewer->getKey())->pluck('user_id')->all());
    }

    /**
     * Comptes bloqués par la personne (leurs messages de groupe lui sont masqués).
     *
     * @return list<int>
     */
    public function blockedBy(User $viewer): array
    {
        return array_map('intval', UserBlock::query()->where('user_id', $viewer->getKey())->pluck('blocked_user_id')->all());
    }

    public function canContact(User $viewer, User $other): bool
    {
        if ($other->is($viewer) || ! $this->reachable($other) || ! $other->can('social.access')) {
            return false;
        }

        $other->loadMissing('profile');

        return ProfileAudience::allows(
            $other->profile?->visibility,
            isSelf: false,
            isReachable: true,
            followsOwner: Follow::query()->where('follower_id', $viewer->getKey())->where('followed_id', $other->getKey())->exists(),
            blocked: $this->blockedBetween($viewer, $other),
        );
    }

    /**
     * @return Collection<int, User>
     */
    public function search(User $viewer, ?string $term, int $limit): Collection
    {
        $id = (int) $viewer->getKey();
        $term = trim((string) $term);
        $following = Follow::query()->select('followed_id')->where('follower_id', $id);
        $followers = Follow::query()->select('follower_id')->where('followed_id', $id);

        $query = User::query()
            ->with('profile')
            ->whereKeyNot($id)
            ->where('status', '!=', UserStatus::Suspended)
            ->notAnonymized()
            ->permission('social.access')
            ->whereNotIn('users.id', UserBlock::query()->select('blocked_user_id')->where('user_id', $id))
            ->whereNotIn('users.id', UserBlock::query()->select('user_id')->where('blocked_user_id', $id))
            ->where(function (Builder $q) use ($following): void {
                $q->whereDoesntHave('profile')
                    ->orWhereHas('profile', fn (Builder $p) => $p->whereNull('visibility')
                        ->orWhereIn('visibility', [ProfileVisibility::Public, ProfileVisibility::Community]))
                    ->orWhere(fn (Builder $f) => $f->whereHas('profile', fn (Builder $p) => $p->where('visibility', ProfileVisibility::Followers))
                        ->whereIn('users.id', $following));
            });

        if ($term === '') {
            $query->where(fn (Builder $q) => $q->whereIn('users.id', $following)->orWhereIn('users.id', $followers));
        } else {
            $query->whereRaw(LikePattern::clause('users.pseudo'), [LikePattern::escape($term).'%'])
                ->where(fn (Builder $q) => $q->whereDoesntHave('profile')
                    ->orWhereHas('profile', fn (Builder $p) => $p->where('searchable', true))
                    ->orWhereIn('users.id', $following));
        }

        return $query->orderBy('users.pseudo')->limit(max(1, $limit))->get();
    }

    private static function id(User|int $user): int
    {
        return $user instanceof User ? (int) $user->getKey() : $user;
    }
}
