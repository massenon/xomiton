<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Taux de complétion du profil progressif (`profiles.completion`, 0–100) : part des groupes de champs renseignés.
 * Règle pure, sans accès aux données : l'appelant fournit un drapeau par groupe.
 */
final class ProfileCompletion
{
    /** @var list<string> */
    public const GROUPS = ['pronouns', 'zone', 'commune', 'bio', 'avatar', 'age_range', 'trusted_contact'];

    private function __construct() {}

    /**
     * @param  array<string, bool>  $filled  groupe => renseigné ?
     */
    public static function fromFlags(array $filled): int
    {
        $done = 0;

        foreach (self::GROUPS as $group) {
            if (! empty($filled[$group])) {
                $done++;
            }
        }

        return (int) round($done * 100 / count(self::GROUPS));
    }
}
