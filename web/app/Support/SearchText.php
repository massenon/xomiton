<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use PDO;
use WeakMap;

/**
 * Forme comparable d'un texte de recherche : minuscules, sans diacritiques, apostrophe typographique ramenée à `'`.
 * Côté PHP, le terme saisi est normalisé par `normalize()` (via LikePattern::contains). Côté base : MySQL / MariaDB
 * (collation utf8mb4_unicode_ci) est déjà insensible aux accents et à la casse ; SQLite ne l'est pas, on y enregistre donc
 * la fonction `xom_fold()` (même normalisation) appliquée à la colonne par LikePattern::clause().
 * Jamais de recherche sur colonne chiffrée (ADR-33) ; aucun terme n'est journalisé.
 */
final class SearchText
{
    public const SQLITE_FUNCTION = 'xom_fold';

    /** @var WeakMap<PDO, true>|null */
    private static ?WeakMap $registered = null;

    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return Str::lower(Str::ascii(str_replace(['’', '‘', 'ʼ'], "'", $text)));
    }

    /** Enregistre `xom_fold()` sur la connexion SQLite (une fois par PDO) ; sans effet sur les autres pilotes. */
    public static function registerSqliteFunction(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();
        self::$registered ??= new WeakMap;

        if (isset(self::$registered[$pdo])) {
            return;
        }

        $pdo->sqliteCreateFunction(
            self::SQLITE_FUNCTION,
            static fn (?string $value): ?string => $value === null ? null : self::normalize($value),
            1,
            PDO::SQLITE_DETERMINISTIC,
        );
        self::$registered[$pdo] = true;
    }
}
