<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Empreinte HMAC-SHA256 avec la clé de l'application (`APP_KEY`) : pseudonymise une valeur à petit espace (IP, identifiant
 * de compte, chemin portant un ULID) qu'un SHA-256 nu laisserait retrouver par force brute (ADR-33, RG-01 / RG-02).
 * Une rotation de `APP_KEY` change toutes les empreintes : à réserver aux données éphémères (idempotence, journaux, limiteurs).
 */
final class KeyedHash
{
    private function __construct() {}

    /** Empreinte hexadécimale (64 caractères), tronquée à `$length` si demandé. */
    public static function make(string $value, ?int $length = null): string
    {
        $digest = hash_hmac('sha256', $value, self::key());

        return $length !== null ? substr($digest, 0, max(1, $length)) : $digest;
    }

    private static function key(): string
    {
        $key = (string) config('app.key');

        return Str::startsWith($key, 'base64:') ? (string) base64_decode(Str::after($key, 'base64:'), true) : $key;
    }
}
