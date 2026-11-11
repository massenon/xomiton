<?php

declare(strict_types=1);

namespace App\Application\Seo;

use Illuminate\Support\Str;

/**
 * Bornes des méta SEO (SEO_CONTENT_GUIDE_INSTRUCTION.md : titre 50–60 caractères suffixe compris, description
 * 140–160) pour les contenus édités : page publique (repli quand `meta_title` / `meta_description` manquent) et seeders,
 * avec la même règle. Coupe toujours sur un mot entier ; `<x-seo>` ajoute « – Xomiton » (10 caractères) au titre.
 *
 *   MetaText::title('Qu’est-ce que la violence basée sur le genre (VBG) ? Repères', 50);
 *   MetaText::description($summary, $bodyHtml, 'Fiche Xomiton pour les filles et femmes LBTQ+ au Bénin.');
 */
final class MetaText
{
    public const TITLE_MAX = 50;

    public const DESCRIPTION_MIN = 140;

    public const DESCRIPTION_MAX = 160;

    /** Titre de page sans suffixe, ≤ $max caractères, coupé au dernier mot entier (avec « … »). */
    public static function title(string $text, int $max = self::TITLE_MAX): string
    {
        $text = self::squish($text);

        return mb_strlen($text) <= $max ? $text : self::cut($text, $max);
    }

    /**
     * Description : le résumé, puis les phrases du corps qui n'y figurent pas déjà jusqu'à $min caractères, bornée à $max
     * (coupe sur un mot) ; complétée par $filler si elle reste sous $min.
     */
    public static function description(string $lead, ?string $bodyHtml = null, ?string $filler = null, int $min = self::DESCRIPTION_MIN, int $max = self::DESCRIPTION_MAX): string
    {
        $text = self::sentence(self::squish($lead));

        // Balises remplacées par une espace : « </p><p> » ne doit pas coller deux phrases.
        foreach (self::sentences(self::squish(strip_tags((string) preg_replace('/<[^>]*>/', ' $0 ', (string) $bodyHtml)))) as $sentence) {
            if (mb_strlen($text) >= $min) {
                break;
            }

            if (! str_contains(mb_strtolower($text), mb_strtolower((string) preg_replace('/[.!?…\s]+$/u', '', $sentence)))) {
                $text = trim($text.' '.$sentence);
            }
        }

        if (mb_strlen($text) < $min && $filler !== null && $filler !== '') {
            $text = trim($text.' '.$filler);
        }

        return mb_strlen($text) <= $max ? $text : self::cut($text, $max);
    }

    private static function squish(string $text): string
    {
        return Str::squish(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Termine la phrase par un point si elle n'a pas de ponctuation finale. */
    private static function sentence(string $text): string
    {
        return $text === '' || preg_match('/[.!?…»]$/u', $text) === 1 ? $text : $text.'.';
    }

    /** @return list<string> */
    private static function sentences(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?…])\s+/u', $text) ?: [])));
    }

    /** Coupe au dernier mot entier pour que le texte, « … » compris, tienne en $max caractères. */
    private static function cut(string $text, int $max): string
    {
        $slice = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($slice, ' ');

        if ($space !== false && $space > (int) ($max / 2)) {
            $slice = mb_substr($slice, 0, $space);
        }

        // Pas de rtrim() : son masque agit octet par octet et amputait « à » (C3 A0) de son second octet (UTF-8 invalide).
        return preg_replace('/[\s\x{00A0},;:.!?\-–(]+$/u', '', $slice).'…';
    }
}
