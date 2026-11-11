<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Paramètres GET des listes publiques à « Charger plus » (P4-03 ; mêmes règles que /thematiques, P4-02) : assainis sans
 * Form Request — un GET invalide redirigerait sans message utile. `q` : texte sans caractères de contrôle, espaces
 * normalisés, 100 caractères au plus ; slug de filtre au format `[a-z0-9-]{1,60}` (sinon false, 404 au contrôleur : pas
 * d'espace d'URL infini) ; `n` : multiple du pas, borné, un pas si non numérique.
 */
final class ListingQuery
{
    public const SEARCH_MAX = 100;

    public static function search(mixed $value, int $max = self::SEARCH_MAX): string
    {
        if (! is_string($value)) {
            return '';
        }

        $clean = (string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $value);

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $clean)), 0, $max);
    }

    /**
     * Slug de filtre : null si absent, false si mal formé (non textuel ou hors `[a-z0-9-]{1,60}`) — le contrôleur répond
     * alors 404 comme pour un slug inconnu ; aucune réponse HTTP n'est décidée ici.
     */
    public static function slug(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) && preg_match('/^[a-z0-9-]{1,60}$/', $value) === 1 ? $value : false;
    }

    /** `n` : multiple du pas, entre un pas et `$max` ; non numérique → un pas. */
    public static function limit(mixed $value, int $step, int $max): int
    {
        $n = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        if ($n === false) {
            return $step;
        }

        return min($max, max($step, (int) ceil($n / $step) * $step));
    }
}
