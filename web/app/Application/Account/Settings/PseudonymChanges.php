<?php

declare(strict_types=1);

namespace App\Application\Account\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Changement de pseudonyme depuis /parametres (P8-09, Q81) : un pseudonyme désigne une personne (/membres/{pseudo},
 * mentions). Après un changement, l'ancien pseudonyme reste **réservé** à son compte (`pseudo_reservation_days`) : aucun
 * autre compte ne peut le prendre (inscription comprise, App\Rules\Pseudo), la personne peut y revenir. Un **délai**
 * sépare deux changements (`pseudo_change_cooldown_hours`) : ni changement en boucle, ni réservation de noms en série.
 * Un changement de casse seule n'est pas un changement. Stockage dans le cache de l'application (file ou database sur
 * cPanel) : une purge du cache lève réservations et délais (table dédiée en reste, Q81).
 */
final class PseudonymChanges
{
    private const RESERVED = 'pseudo_reserved|';

    private const COOLDOWN = 'pseudo_cooldown|';

    public static function differs(string $current, string $next): bool
    {
        return mb_strtolower(trim($current)) !== mb_strtolower(trim($next));
    }

    /** Compte auquel ce pseudonyme est réservé, ou null. */
    public function reservedFor(string $pseudo): ?int
    {
        $owner = Cache::get(self::RESERVED.mb_strtolower(trim($pseudo)));

        return is_numeric($owner) ? (int) $owner : null;
    }

    /** Secondes avant le prochain changement permis (0 : permis). */
    public function cooldownRemaining(User $user): int
    {
        $until = Cache::get(self::COOLDOWN.$user->getKey());

        return is_numeric($until) ? max(0, (int) $until - now()->getTimestamp()) : 0;
    }

    /** Après un changement enregistré : réserve l'ancien pseudonyme et ouvre le délai. */
    public function record(User $user, string $previous): void
    {
        $days = max(0, (int) config('xomiton.accounts.pseudo_reservation_days', 30));
        $hours = max(0, (int) config('xomiton.accounts.pseudo_change_cooldown_hours', 24));

        if ($days > 0) {
            Cache::put(self::RESERVED.mb_strtolower(trim($previous)), (int) $user->getKey(), now()->addDays($days));
        }

        if ($hours > 0) {
            $until = now()->addHours($hours);
            Cache::put(self::COOLDOWN.$user->getKey(), $until->getTimestamp(), $until);
        }
    }
}
