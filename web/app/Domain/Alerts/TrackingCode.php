<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

use InvalidArgumentException;

/**
 * Code de suivi d'une alerte (FR-ALT-01, P5-01) : 10 caractères tirés d'un alphabet sans ambiguïté (ni 0/O, ni 1/I),
 * attribué par le serveur à la création et affiché « SIG-XXXXXXXXXX ». 32¹⁰ ≈ 1,1 × 10¹⁵ combinaisons : l'énumération
 * est impraticable sous le limiteur `tracking`. Pur : l'unicité en base est vérifiée par le modèle Alert.
 */
final class TrackingCode
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 10;

    public const PREFIX = 'SIG-';

    public const PATTERN = '/^[A-HJ-NP-Z2-9]{10}$/';

    private function __construct() {}

    /**
     * Code aléatoire ; `$randomInt(int $min, int $max): int` est injectable (tests), `random_int` par défaut.
     *
     * @param  (callable(int, int): int)|null  $randomInt
     */
    public static function random(int $length = self::LENGTH, ?callable $randomInt = null): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Tracking code length must be at least 1.');
        }

        $randomInt ??= random_int(...);
        $max = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[$randomInt(0, $max)];
        }

        return $code;
    }

    /**
     * Saisie tolérante : majuscules, espaces et tirets retirés, préfixe « SIG- » facultatif. Null si le résultat
     * n'est pas un code valide (jamais d'exception sur une saisie de l'utilisateur·rice).
     */
    public static function normalize(string $input): ?string
    {
        $code = strtoupper(trim($input));
        $code = preg_replace('/[\s\-_.]+/u', '', $code) ?? '';

        if (str_starts_with($code, 'SIG')) {
            $candidate = substr($code, 3);

            if (self::isValid($candidate)) {
                return $candidate;
            }
        }

        return self::isValid($code) ? $code : null;
    }

    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1;
    }

    /** Forme affichée (lang/fr/alerts.php `report.tracking.code_placeholder`, admin « Alerte :code »). */
    public static function display(string $code): string
    {
        return self::PREFIX.$code;
    }
}
