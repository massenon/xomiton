<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Enums\AgeRange;

/**
 * Tranches d'âge que la personne inscrite peut choisir sur SON profil (décision client du 11/11/2026 : âge minimal
 * d'inscription 18 ans, `xomiton.accounts.min_age`). `AgeRange` garde toutes les tranches (10-14, 15-17 décrivent des
 * personnes accompagnées dans les dossiers saisis par les pair·es) ; seule la liste proposée au membre est filtrée :
 * une tranche reste proposée quand elle contient au moins un âge autorisé (tranche ouverte comprise).
 */
final class MemberAgeRanges
{
    private function __construct() {}

    /**
     * @return list<AgeRange>
     */
    public static function cases(?int $minAge = null): array
    {
        $minAge ??= (int) config('xomiton.accounts.min_age', 18);

        return array_values(array_filter(
            AgeRange::cases(),
            static fn (AgeRange $range): bool => $range->upperBound() === null || $range->upperBound() >= $minAge,
        ));
    }

    /**
     * @return list<string>
     */
    public static function values(?int $minAge = null): array
    {
        return array_map(static fn (AgeRange $range): string => $range->value, self::cases($minAge));
    }
}
