<?php

declare(strict_types=1);

namespace App\Domain\Cases;

/**
 * Orientation vers un service (`cases.referral`, prototype collecte.php l.108) : Point focal, Juriste, Psychologue,
 * Gynécologue, Hébergement. Valeurs stockées telles quelles (libellés du prototype, repris par l'import) ; il ne
 * s'agit pas de l'orientation sexuelle, absente de la collecte. Champ facultatif (« Aucune »).
 */
final class CaseReferral
{
    /** @var list<string> */
    public const OPTIONS = ['Point focal', 'Juriste', 'Psychologue', 'Gynécologue', 'Hébergement'];

    private function __construct() {}

    public static function isValid(string $value): bool
    {
        return in_array($value, self::OPTIONS, true);
    }
}
