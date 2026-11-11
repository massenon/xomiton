<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Durée de lecture affichée (« 4 min de lecture », prototype blog-details.php l.38) : `duration_minutes` saisi par
 * l'équipe, sinon estimée sur le corps HTML (texte seul, 200 mots par minute), au moins une minute.
 */
final class ReadingTime
{
    public const WORDS_PER_MINUTE = 200;

    private function __construct() {}

    public static function minutes(?int $declared, ?string $html): int
    {
        if ($declared !== null && $declared > 0) {
            return $declared;
        }

        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }
}
