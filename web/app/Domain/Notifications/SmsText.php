<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * Texte d'un SMS compté en segments (P5-03, D6 : plafond de coût). Un seul caractère hors de l'alphabet GSM 03.38
 * (apostrophe courbe, tiret demi-cadratin, espace insécable, « ê ») fait passer tout le message en UCS-2 :
 * 70 caractères par segment au lieu de 160. `normalize()` ramène donc la typographie des textes de lang/fr (qui garde
 * ses espaces insécables et apostrophes) et les lettres accentuées absentes de GSM 03.38 vers leur équivalent GSM-7 ;
 * ce qui reste hors alphabet (emoji d'un pseudo, autre écriture) est compté en UCS-2.
 */
final class SmsText
{
    public const GSM_SINGLE = 160;

    public const GSM_MULTI = 153;

    public const UCS2_SINGLE = 70;

    public const UCS2_MULTI = 67;

    /** Alphabet de base GSM 03.38 (un septet chacun). */
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** Table d'extension (deux septets chacun : échappement + caractère). */
    private const GSM_EXTENDED = '^{}\\[~]|€';

    /** Équivalents GSM-7 de la typographie française et des lettres hors alphabet. */
    private const REPLACEMENTS = [
        "\u{2019}" => "'", "\u{2018}" => "'", "\u{02BC}" => "'", "\u{201C}" => '"', "\u{201D}" => '"',
        '«' => '"', '»' => '"', "\u{2013}" => '-', "\u{2014}" => '-', "\u{2011}" => '-', "\u{2010}" => '-',
        "\u{00A0}" => ' ', "\u{202F}" => ' ', "\u{2009}" => ' ', "\u{2026}" => '...', '·' => '.', "\u{2022}" => '-',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ë' => 'e', 'ï' => 'i', 'ÿ' => 'y', 'ç' => 'c',
        'á' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'œ' => 'oe', 'À' => 'A', 'Â' => 'A', 'È' => 'E', 'Ê' => 'E',
        'Ë' => 'E', 'Î' => 'I', 'Ï' => 'I', 'Ô' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Œ' => 'OE',
    ];

    private function __construct() {}

    public static function normalize(string $text): string
    {
        return strtr($text, self::REPLACEMENTS);
    }

    public static function isGsm7(string $text): bool
    {
        foreach (self::chars($text) as $char) {
            if (! str_contains(self::GSM_BASIC, $char) && ! str_contains(self::GSM_EXTENDED, $char)) {
                return false;
            }
        }

        return true;
    }

    /** Longueur en unités de l'encodage du texte (septets GSM-7, ou unités UTF-16 en UCS-2). */
    public static function length(string $text, ?bool $gsm = null): int
    {
        $gsm ??= self::isGsm7($text);

        return array_sum(array_map(static fn (string $char): int => self::units($char, $gsm), self::chars($text)));
    }

    public static function segments(string $text): int
    {
        $gsm = self::isGsm7($text);
        $length = self::length($text, $gsm);

        if ($length <= ($gsm ? self::GSM_SINGLE : self::UCS2_SINGLE)) {
            return 1;
        }

        return (int) ceil($length / ($gsm ? self::GSM_MULTI : self::UCS2_MULTI));
    }

    /** Unités disponibles en `$segments` segments pour l'encodage de `$text`. */
    public static function capacity(string $text, int $segments): int
    {
        $gsm = self::isGsm7($text);
        $segments = max(1, $segments);

        if ($segments === 1) {
            return $gsm ? self::GSM_SINGLE : self::UCS2_SINGLE;
        }

        return $segments * ($gsm ? self::GSM_MULTI : self::UCS2_MULTI);
    }

    /** Tronque à `$max` unités (points de suspension GSM-7 compris), sans couper un caractère. */
    public static function limit(string $text, int $max, string $end = '...', ?bool $gsm = null): string
    {
        $gsm ??= self::isGsm7($text);

        if (self::length($text, $gsm) <= $max) {
            return $text;
        }

        $room = max(0, $max - self::length($end, $gsm));
        $kept = '';
        $used = 0;

        foreach (self::chars($text) as $char) {
            $units = self::units($char, $gsm);

            if ($used + $units > $room) {
                break;
            }

            $kept .= $char;
            $used += $units;
        }

        return rtrim($kept).$end;
    }

    /**
     * @return list<string>
     */
    private static function chars(string $text): array
    {
        return mb_str_split($text);
    }

    private static function units(string $char, bool $gsm): int
    {
        if ($gsm) {
            return str_contains(self::GSM_EXTENDED, $char) ? 2 : 1;
        }

        return strlen((string) mb_convert_encoding($char, 'UTF-16BE', 'UTF-8')) / 2 > 1 ? 2 : 1;
    }
}
