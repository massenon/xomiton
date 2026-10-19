<?php

declare(strict_types=1);

namespace App\Application\Community\Network;

use App\Domain\Community\Exceptions\FollowUnavailable;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Suivre / ne plus suivre un·e membre (P8-06, `follows`) : idempotent (unique `(follower_id, followed_id)`, un rejeu rend
 * `changed: false`). Suivre exige un compte suivable (NetworkPeople::canFollow : actif, sans blocage, profil ouvert au
 * réseau), sinon 422 `follow_unavailable` — même réponse pour un compte inconnu, bloqué ou réservé à ses connexions.
 * Ne plus suivre est toujours permis, même après un blocage (on peut toujours défaire un lien). Aucune notification :
 * le catalogue P3-02 n'en prévoit pas pour un abonnement.
 */
final class FollowMember
{
    public function __construct(private readonly NetworkPeople $people) {}

    /**
     * @return array{user_id: int, following: bool, changed: bool}
     */
    public function follow(User $viewer, int $targetId): array
    {
        $target = User::query()->with('profile')->find($targetId);

        if (! $target instanceof User || ! $this->people->canFollow($viewer, $target)) {
            throw new FollowUnavailable;
        }

        $inserted = DB::table('follows')->insertOrIgnore([
            'follower_id' => (int) $viewer->getKey(),
            'followed_id' => $targetId,
            'created_at' => now(),
        ]);

        return ['user_id' => $targetId, 'following' => true, 'changed' => $inserted > 0];
    }

    /**
     * @return array{user_id: int, following: bool, changed: bool}
     */
    public function unfollow(User $viewer, int $targetId): array
    {
        $deleted = Follow::query()->between((int) $viewer->getKey(), $targetId)->delete();

        return ['user_id' => $targetId, 'following' => false, 'changed' => $deleted > 0];
    }
}
