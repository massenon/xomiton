<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Typographie française des textes semés ou importés (P4-01) : apostrophe courbe ’ et espace insécable U+00A0 avant
 * « ? ! : ; ». Le seeder écrit la forme typographique, le JSON du prototype garde souvent l'apostrophe droite et
 * l'espace ordinaire : `variants()` donne toutes les graphies d'une même question pour la retrouver sans la dupliquer.
 *
 *   FrenchTypography::apply("Qu'est-ce que Xomiton ?");      // « Qu’est-ce que Xomiton ? » (U+00A0 avant « ? »)
 *   Faq::query()->whereIn('question', FrenchTypography::variants($question))->first();
 */
final class FrenchTypography
{
    public const NBSP = "\u{00A0}";

    public static function apply(string $text): string
    {
        return (string) preg_replace('/ ([?!:;])/u', self::NBSP.'$1', str_replace("'", '’', $text));
    }

    /** Forme d'avant l'harmonisation : apostrophe droite, espaces ordinaires. */
    public static function plain(string $text): string
    {
        return str_replace(['’', self::NBSP], ["'", ' '], $text);
    }

    /**
     * Graphies équivalentes d'un texte (telle quelle, typographique, ordinaire), sans doublon.
     *
     * @return list<string>
     */
    public static function variants(string $text): array
    {
        return array_values(array_unique([$text, self::apply($text), self::plain($text)]));
    }
}
