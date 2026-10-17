<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Version des textes de consentement horodatés dans `consents.version` (CGU + charte, confidentialité, données
 * sensibles, newsletter). À incrémenter quand un texte change : les anciens consentements restent tracés.
 */
final class ConsentVersion
{
    public const DEFAULT = '2026-10';

    private function __construct() {}

    public static function current(): string
    {
        $configured = config('xomiton.consents.version');

        return is_string($configured) && $configured !== '' ? mb_substr($configured, 0, 10) : self::DEFAULT;
    }
}
