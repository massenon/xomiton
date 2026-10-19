<?php

declare(strict_types=1);

namespace App\Domain\Cases;

/**
 * Notes d'un dossier (`cases.notes`, chiffrées) : vides ou d'au moins 20 caractères (règle `min_len_20`, prototype
 * collecte.php l.31), espaces de début et de fin ignorés ; jamais de nom ni de coordonnées (avertissement affiché
 * par la collecte, P9-04). Longueur maximale : garde-fou de taille, la colonne chiffrée est un `text`.
 */
final class CaseNotes
{
    public const MIN_LENGTH = 20;

    public const MAX_LENGTH = 5000;

    private function __construct() {}

    /** Texte vide ou significatif (`min_len_20`) : `true` pour null, '' et ≥ 20 caractères. */
    public static function isAcceptable(?string $notes, int $min = self::MIN_LENGTH): bool
    {
        $length = mb_strlen(trim((string) $notes));

        return $length === 0 || $length >= $min;
    }

    /** Valeur stockée : texte rogné, null s'il est vide. */
    public static function normalize(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : $notes;
    }
}
