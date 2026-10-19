<?php

declare(strict_types=1);

namespace App\Domain\Cases;

/**
 * Étapes de suivi d'un dossier (`case_followups.step`, prototype admin/suivi.php l.7), stockées par leur libellé
 * comme l'import du prototype. La date d'une étape n'est jamais future (RG-07, règle `date_not_future`, catalogue :
 * « Suivi ») ; la prochaine échéance (`next_on`) peut l'être, jamais avant la date de l'étape.
 */
final class FollowupStep
{
    /** @var list<string> */
    public const OPTIONS = [
        'Accueil',
        'Écoute',
        'Orientation médicale',
        'Appui psychologique',
        'Appui juridique',
        'Hébergement',
        'Suivi à 30 jours',
        'Suivi à 90 jours',
        'Clôture',
    ];

    /** Commentaire d'une étape (prototype : tronqué à 1 000 caractères ; refusé au-delà ici). */
    public const COMMENT_MAX_LENGTH = 1000;

    private function __construct() {}

    public static function isValid(string $value): bool
    {
        return in_array($value, self::OPTIONS, true);
    }
}
