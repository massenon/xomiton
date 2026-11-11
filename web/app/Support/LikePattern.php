<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Recherche « contient » par LIKE sans jokers venus de la saisie : `%`, `_` et le caractère d'échappement sont
 * neutralisés, et la clause déclare `ESCAPE '!'` — un caractère sans sens particulier pour MySQL / MariaDB ni SQLite
 * (la barre oblique inverse est déjà une séquence d'échappement des chaînes MySQL). Usage :
 * `$query->whereRaw(LikePattern::clause('title'), [LikePattern::contains($q)])`.
 */
final class LikePattern
{
    public const ESCAPE = '!';

    /** Motif « contient » : la saisie échappée entre deux `%`. */
    public static function contains(string $term): string
    {
        return '%'.self::escape(SearchText::normalize($term)).'%';
    }

    /** Préfixe « commence par » (même normalisation que contains). */
    public static function startsWith(string $term): string
    {
        return self::escape(SearchText::normalize($term)).'%';
    }

    public static function escape(string $term): string
    {
        return str_replace([self::ESCAPE, '%', '_'], [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'], $term);
    }

    /** Fragment SQL `<colonne> like ? escape '!'` ; `$column` est un nom de colonne du code, jamais une saisie. */
    public static function clause(string $column): string
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'sqlite') {
            SearchText::registerSqliteFunction($connection);
            $column = SearchText::SQLITE_FUNCTION.'('.$column.')';
        } else {
            $column = "replace({$column}, '’', '''')"; // la collation _ci gère déjà casse et accents
        }

        return $column." like ? escape '".self::ESCAPE."'";
    }
}
