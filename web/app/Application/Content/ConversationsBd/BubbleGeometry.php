<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Enums\BubbleTailTarget;
use App\Enums\BubbleType;

/**
 * Géométrie des bulles des conversations en BD (P20-04, GRAMMAIRE_DES_BULLES.md §2 à §5). Une case mesure 1200 × 800
 * unités (format 3:2) : le SVG est mis à l'échelle de la case, positions et queues en % (rendu identique de 320 à 1600 px).
 *
 * La bulle se dimensionne d'après son texte, jamais l'inverse : coupure des lignes au mot (longueur de ligne adaptée à la
 * longueur du texte pour une bulle horizontale), largeur estimée par une chasse moyenne de la police, marge intérieure
 * 0,6 em × 0,45 em, puis la forme du type autour du bloc de texte. `position` = coin haut gauche de l'encombrement de la
 * bulle (forme comprise, queue exclue).
 *
 * **Jumeau TypeScript** : resources/js/components/bd/bubbles.ts reproduit ces calculs opération par opération (mêmes
 * constantes, table trigonométrique figée, arrondi au dixième « demi vers l'extérieur ») pour que le composant Vue de
 * l'éditeur dessine exactement le même SVG que Blade (test de parité `bd-panel`). Toute modification se fait des deux
 * côtés.
 */
final class BubbleGeometry
{
    public const WIDTH = 1200;

    public const HEIGHT = 800;

    /** Taille de base du texte, en unités de case (≈ 15 px pour une case de 800 px de large). */
    public const BASE_FONT = 22;

    public const LINE_HEIGHT = 1.25;

    public const INK = '#1B1630';

    public const PAPER = '#FFFFFF';

    public const CAPTION_PAPER = '#FBF7FA';

    public const MASKED_PAPER = '#E4E1E8';

    public const MASKED_BAR = '#B9B4C2';

    public const FONT_TEXT = "Arial, 'Liberation Sans', Helvetica, sans-serif";

    public const FONT_STRONG = "Montserrat, Arial, 'Liberation Sans', sans-serif";

    /** cos(k × 15°), k = 0…23, arrondis à 4 décimales (sin(k) = COS[(k + 18) % 24]) : aucun appel à cos / sin. */
    public const COS = [1, 0.9659, 0.866, 0.7071, 0.5, 0.2588, 0, -0.2588, -0.5, -0.7071, -0.866, -0.9659, -1, -0.9659, -0.866, -0.7071, -0.5, -0.2588, 0, 0.2588, 0.5, 0.7071, 0.866, 0.9659];

    /** Variation du rayon de la bulle tremblée (contour irrégulier), répétée sur les 24 points. */
    private const SHAKE = [1.02, 0.95, 1.03, 0.96, 1.01, 0.94];

    public static function sin(int $k): float
    {
        return (float) self::COS[($k + 18) % 24];
    }

    public static function cos(int $k): float
    {
        return (float) self::COS[$k % 24];
    }

    /** Arrondi au dixième, demi vers l'extérieur, identique en PHP et en JavaScript. */
    public static function round1(float $value): float
    {
        $sign = $value < 0 ? -1 : 1;

        return $sign * floor(abs($value) * 10 + 0.5) / 10;
    }

    /** Nombre formaté pour un attribut SVG (« 12.5 », « 12 », jamais « -0 »). */
    public static function fmt(float $value): string
    {
        $n = self::round1($value);

        if ($n == 0.0) {
            return '0';
        }

        return rtrim(rtrim(sprintf('%.1f', $n), '0'), '.');
    }

    public static function isRect(BubbleType $type): bool
    {
        return in_array($type, [BubbleType::Message, BubbleType::DeviceVoice, BubbleType::Caption], true);
    }

    public static function fontScale(BubbleType $type): float
    {
        return match ($type) {
            BubbleType::Shout => 1.3,
            BubbleType::Shaky, BubbleType::Whisper => 0.85,
            default => 1.0,
        };
    }

    /** Chasse moyenne d'un caractère, en em : Montserrat 700 (cri, cartouche) plus large que la pile Arial. */
    public static function charFactor(BubbleType $type): float
    {
        return in_array($type, [BubbleType::Shout, BubbleType::Caption], true) ? 0.64 : 0.53;
    }

    /** Débord de la forme autour de la demi-largeur `a` / demi-hauteur `b` (nuage, pointes du cri). */
    public static function outer(BubbleType $type): float
    {
        return match ($type) {
            BubbleType::Thought, BubbleType::Joy => 1.08,
            BubbleType::Shout => 1.2,
            BubbleType::Speech, BubbleType::Whisper => 1.02,
            BubbleType::Shaky => 1.03,
            default => 1.0,
        };
    }

    public static function strokeWidth(BubbleType $type): float
    {
        return match ($type) {
            BubbleType::Shout => 4.5,
            BubbleType::Caption => 2,
            default => 2.5,
        };
    }

    /**
     * Mots du texte, coupés aux seules espaces ASCII : une espace insécable (« Pourquoi ? ») ne coupe jamais une ligne.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $parts = preg_split('/[ \t\n\r\f\v]+/', $text) ?: [];

        return array_values(array_filter($parts, static fn (string $w): bool => $w !== ''));
    }

    public static function length(string $text): int
    {
        return mb_strlen($text);
    }

    /** Mot débarrassé de la ponctuation de bord, en minuscules : comparaison des emphases. */
    public static function bare(string $word): string
    {
        return mb_strtolower((string) preg_replace('/^[\p{P}\p{S}\p{Z}]+|[\p{P}\p{S}\p{Z}]+$/u', '', $word));
    }

    /**
     * Rangs des mots mis en gras : chaque emphase (un ou deux mots, §5) est cherchée comme une suite de mots du texte.
     *
     * @param  list<string>  $words
     * @param  list<string>  $emphasis
     * @return array<int, true>
     */
    public static function boldIndexes(array $words, array $emphasis): array
    {
        $bare = array_map(self::bare(...), $words);
        $bold = [];

        foreach ($emphasis as $phrase) {
            $needle = array_map(self::bare(...), self::words($phrase));
            $size = count($needle);

            if ($size === 0) {
                continue;
            }

            for ($i = 0; $i + $size <= count($bare); $i++) {
                if (array_slice($bare, $i, $size) === $needle) {
                    for ($j = $i; $j < $i + $size; $j++) {
                        $bold[$j] = true;
                    }

                    break;
                }
            }
        }

        return $bold;
    }

    /** Longueur de ligne visée (en caractères) : bloc de texte nettement plus large que haut (bulle horizontale, §2). */
    public static function maxChars(BubbleType $type, int $length): int
    {
        if ($type === BubbleType::Caption) {
            return max(16, min(48, (int) floor(4.2 * sqrt($length) + 0.5)));
        }

        return max(12, min(30, (int) floor(3 * sqrt($length) + 0.5)));
    }

    /**
     * Lignes du texte, coupées au mot.
     *
     * @param  list<string>  $emphasis
     * @return list<list<array{text: string, bold: bool}>>
     */
    public static function lines(BubbleType $type, string $text, array $emphasis = []): array
    {
        $words = self::words($text);

        if ($words === []) {
            return [];
        }

        $bold = self::boldIndexes($words, $emphasis);
        $max = self::maxChars($type, self::length(implode(' ', $words)));
        $lines = [];
        $current = [];
        $currentLength = 0;

        foreach ($words as $index => $word) {
            $wordLength = self::length($word);
            $candidate = $current === [] ? $wordLength : $currentLength + 1 + $wordLength;

            if ($current !== [] && $candidate > $max) {
                $lines[] = $current;
                $current = [];
                $candidate = $wordLength;
            }

            $current[] = ['text' => $word, 'bold' => isset($bold[$index])];
            $currentLength = $candidate;
        }

        $lines[] = $current;

        return $lines;
    }

    /** @param  list<array{text: string, bold: bool}>  $line */
    public static function lineLength(array $line): int
    {
        return self::length(implode(' ', array_column($line, 'text')));
    }

    /**
     * Mise en page d'une bulle au format de la grammaire (§6), en unités de case.
     *
     * @param  array<string, mixed>  $bubble
     * @return array{type: BubbleType, font: float, lines: list<list<array{text: string, bold: bool}>>, textW: float, textH: float, a: float, b: float, w: float, h: float, x: float, y: float, cx: float, cy: float, outer: float, masked: bool}
     */
    public static function layout(array $bubble): array
    {
        $type = BubbleType::tryFrom((string) ($bubble['type'] ?? '')) ?? BubbleType::Speech;
        $masked = (bool) ($bubble['masque'] ?? false);
        $font = self::BASE_FONT * self::fontScale($type);
        $emphasis = array_values(array_filter((array) ($bubble['emphase'] ?? []), 'is_string'));
        $lines = $masked ? [] : self::lines($type, (string) ($bubble['texte'] ?? ''), $emphasis);
        $longest = 0;

        foreach ($lines as $line) {
            $longest = max($longest, self::lineLength($line));
        }

        $textW = $longest * $font * self::charFactor($type);
        $textH = count($lines) * $font * self::LINE_HEIGHT;

        if ($masked || $lines === []) {
            $textW = 180;
            $textH = $font * self::LINE_HEIGHT * 2;
        }

        $padX = 0.6 * $font;
        $padY = 0.45 * $font;
        $outer = self::outer($type);

        if (self::isRect($type)) {
            $a = $textW / 2 + $padX;
            $b = $textH / 2 + $padY;
        } else {
            $a = $textW / 2 * 1.3 + $padX;
            $b = $textH / 2 * 1.3 + $padY;

            if ($type === BubbleType::Thought || $type === BubbleType::Joy) {
                $a = $a * 1.06;
                $b = $b * 1.06;
            }
        }

        $w = 2 * $a * $outer;
        $h = 2 * $b * $outer;
        $position = (array) ($bubble['position'] ?? []);
        $x = (float) ($position['x'] ?? 0) * self::WIDTH / 100;
        $y = (float) ($position['y'] ?? 0) * self::HEIGHT / 100;

        return [
            'type' => $type,
            'font' => $font,
            'lines' => $lines,
            'textW' => $textW,
            'textH' => $textH,
            'a' => $a,
            'b' => $b,
            'w' => $w,
            'h' => $h,
            'x' => $x,
            'y' => $y,
            'cx' => $x + $w / 2,
            'cy' => $y + $h / 2,
            'outer' => $outer,
            'masked' => $masked,
        ];
    }

    /**
     * Encombrement de la bulle en % de la case (contrôles de la grammaire, §7).
     *
     * @param  array<string, mixed>  $bubble
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function boxPercent(array $bubble): array
    {
        $l = self::layout($bubble);

        return [
            'x' => $l['x'] / self::WIDTH * 100,
            'y' => $l['y'] / self::HEIGHT * 100,
            'w' => $l['w'] / self::WIDTH * 100,
            'h' => $l['h'] / self::HEIGHT * 100,
        ];
    }

    /**
     * Pointe de la queue en unités de case, ou null (cartouche, queue absente). Bord de la case : la pointe est posée sur
     * le bord, en face du centre de la bulle, sauf coordonnées explicites.
     *
     * @param  array<string, mixed>  $bubble
     * @param  array{cx: float, cy: float}  $layout
     * @return array{x: float, y: float}|null
     */
    public static function tip(array $bubble, array $layout): ?array
    {
        $tail = $bubble['queue'] ?? null;
        $type = BubbleType::tryFrom((string) ($bubble['type'] ?? ''));

        if ($type === BubbleType::Caption || ! is_array($tail)) {
            return null;
        }

        $target = BubbleTailTarget::tryFrom((string) ($tail['vers'] ?? ''));

        if ($target === null) {
            return null;
        }

        $hasX = is_numeric($tail['x'] ?? null);
        $hasY = is_numeric($tail['y'] ?? null);
        $x = $hasX ? (float) $tail['x'] * self::WIDTH / 100 : $layout['cx'];
        $y = $hasY ? (float) $tail['y'] * self::HEIGHT / 100 : $layout['cy'];

        return match ($target) {
            BubbleTailTarget::EdgeLeft => ['x' => $hasX ? $x : 0.0, 'y' => $y],
            BubbleTailTarget::EdgeRight => ['x' => $hasX ? $x : (float) self::WIDTH, 'y' => $y],
            BubbleTailTarget::EdgeTop => ['x' => $x, 'y' => $hasY ? $y : 0.0],
            BubbleTailTarget::EdgeBottom => ['x' => $x, 'y' => $hasY ? $y : (float) self::HEIGHT],
            default => $hasX && $hasY ? ['x' => $x, 'y' => $y] : null,
        };
    }

    /**
     * Distance du centre au contour dans la direction (ux, uy) normalisée.
     *
     * @param  array{type: BubbleType, a: float, b: float}  $l
     */
    public static function edgeDistance(array $l, float $ux, float $uy): float
    {
        if (self::isRect($l['type'])) {
            $sx = abs($ux) > 0.000001 ? $l['a'] / abs($ux) : 1000000.0;
            $sy = abs($uy) > 0.000001 ? $l['b'] / abs($uy) : 1000000.0;

            return min($sx, $sy);
        }

        return 1 / sqrt(($ux / $l['a']) * ($ux / $l['a']) + ($uy / $l['b']) * ($uy / $l['b']));
    }

    /**
     * Contour de la bulle (attribut `d`).
     *
     * @param  array{type: BubbleType, a: float, b: float, cx: float, cy: float, w: float, h: float, x: float, y: float}  $l
     */
    public static function bodyPath(array $l): string
    {
        $f = self::fmt(...);
        $cx = $l['cx'];
        $cy = $l['cy'];
        $a = $l['a'];
        $b = $l['b'];

        switch ($l['type']) {
            case BubbleType::Thought:
            case BubbleType::Joy:
                $n = $a + $b > 260 ? 12 : 8;
                $step = intdiv(24, $n);
                $points = [];
                for ($k = 0; $k < $n; $k++) {
                    $points[] = [$cx + $a * 0.94 * self::cos($k * $step), $cy + $b * 0.94 * self::sin($k * $step)];
                }
                $d = 'M'.$f($points[0][0]).' '.$f($points[0][1]);
                for ($k = 0; $k < $n; $k++) {
                    $p = $points[$k];
                    $q = $points[($k + 1) % $n];
                    $chord = sqrt(($q[0] - $p[0]) * ($q[0] - $p[0]) + ($q[1] - $p[1]) * ($q[1] - $p[1]));
                    $r = $chord * 0.6;
                    $d .= ' A'.$f($r).' '.$f($r).' 0 0 1 '.$f($q[0]).' '.$f($q[1]);
                }

                return $d.' Z';

            case BubbleType::Shout:
                $d = '';
                for ($k = 0; $k < 24; $k++) {
                    $factor = $k % 2 === 0 ? 1.2 : 0.97;
                    $d .= ($k === 0 ? 'M' : ' L').$f($cx + $a * $factor * self::cos($k)).' '.$f($cy + $b * $factor * self::sin($k));
                }

                return $d.' Z';

            case BubbleType::Shaky:
                // Contour tremblé : courbes passant par les milieux de 24 points de rayon irrégulier.
                $points = [];
                for ($k = 0; $k < 24; $k++) {
                    $factor = self::SHAKE[$k % 6];
                    $points[] = [$cx + $a * $factor * self::cos($k), $cy + $b * $factor * self::sin($k)];
                }
                $mid = static fn (array $p, array $q): array => [($p[0] + $q[0]) / 2, ($p[1] + $q[1]) / 2];
                $start = $mid($points[0], $points[1]);
                $d = 'M'.$f($start[0]).' '.$f($start[1]);
                for ($k = 1; $k <= 24; $k++) {
                    $control = $points[$k % 24];
                    $end = $mid($control, $points[($k + 1) % 24]);
                    $d .= ' Q'.$f($control[0]).' '.$f($control[1]).' '.$f($end[0]).' '.$f($end[1]);
                }

                return $d.' Z';

            case BubbleType::Message:
                return self::roundedRect($l['x'], $l['y'], $l['w'], $l['h'], 16);

            case BubbleType::DeviceVoice:
                return self::roundedRect($l['x'], $l['y'], $l['w'], $l['h'], 6);

            case BubbleType::Caption:
                return 'M'.$f($l['x']).' '.$f($l['y']).' H'.$f($l['x'] + $l['w']).' V'.$f($l['y'] + $l['h']).' H'.$f($l['x']).' Z';

            default:
                return 'M'.$f($cx - $a).' '.$f($cy).' A'.$f($a).' '.$f($b).' 0 1 0 '.$f($cx + $a).' '.$f($cy).' A'.$f($a).' '.$f($b).' 0 1 0 '.$f($cx - $a).' '.$f($cy).' Z';
        }
    }

    public static function roundedRect(float $x, float $y, float $w, float $h, float $r): string
    {
        $f = self::fmt(...);
        $r = min($r, $w / 2, $h / 2);

        return 'M'.$f($x + $r).' '.$f($y)
            .' H'.$f($x + $w - $r).' A'.$f($r).' '.$f($r).' 0 0 1 '.$f($x + $w).' '.$f($y + $r)
            .' V'.$f($y + $h - $r).' A'.$f($r).' '.$f($r).' 0 0 1 '.$f($x + $w - $r).' '.$f($y + $h)
            .' H'.$f($x + $r).' A'.$f($r).' '.$f($r).' 0 0 1 '.$f($x).' '.$f($y + $h - $r)
            .' V'.$f($y + $r).' A'.$f($r).' '.$f($r).' 0 0 1 '.$f($x + $r).' '.$f($y).' Z';
    }

    /**
     * Queue : triangle pointu (parole, cri, joie…) ou trois ronds décroissants (pensée), vers la pointe.
     *
     * @param  array{type: BubbleType, a: float, b: float, cx: float, cy: float}  $l
     * @param  array{x: float, y: float}  $tip
     * @return array{path: string|null, circles: list<array{cx: float, cy: float, r: float}>}
     */
    public static function tail(array $l, array $tip): array
    {
        $none = ['path' => null, 'circles' => []];
        $dx = $tip['x'] - $l['cx'];
        $dy = $tip['y'] - $l['cy'];
        $dist = sqrt($dx * $dx + $dy * $dy);

        if ($dist < 0.000001) {
            return $none;
        }

        $ux = $dx / $dist;
        $uy = $dy / $dist;
        $s = self::edgeDistance($l, $ux, $uy);

        if ($dist <= $s * 1.02) {
            return $none;
        }

        if ($l['type'] === BubbleType::Thought) {
            $ex = $l['cx'] + $ux * $s * 1.04;
            $ey = $l['cy'] + $uy * $s * 1.04;
            $r0 = max(6, min($l['a'], $l['b']) * 0.16);
            $circles = [];
            foreach ([[0.3, 1.0], [0.6, 0.66], [0.88, 0.4]] as [$t, $k]) {
                $circles[] = ['cx' => $ex + ($tip['x'] - $ex) * $t, 'cy' => $ey + ($tip['y'] - $ey) * $t, 'r' => $r0 * $k];
            }

            return ['path' => null, 'circles' => $circles];
        }

        $half = min(24, min($l['a'], $l['b']) * 0.3);
        $bx = $l['cx'] + $ux * $s * 0.75;
        $by = $l['cy'] + $uy * $s * 0.75;
        $px = -$uy * $half;
        $py = $ux * $half;
        $f = self::fmt(...);

        return [
            'path' => 'M'.$f($bx + $px).' '.$f($by + $py).' L'.$f($tip['x']).' '.$f($tip['y']).' L'.$f($bx - $px).' '.$f($by - $py).' Z',
            'circles' => [],
        ];
    }

    /**
     * Petites marques d'ondes de la voix d'un appareil, de part et d'autre du rectangle.
     *
     * @param  array{cx: float, cy: float, x: float, w: float}  $l
     */
    public static function wavesPath(array $l): string
    {
        $f = self::fmt(...);
        $d = [];

        foreach ([1, 2] as $i) {
            $right = $l['x'] + $l['w'] + 4 + 7 * $i;
            $left = $l['x'] - 4 - 7 * $i;
            $half = 9 * $i;
            $r = 12 * $i;
            $d[] = 'M'.$f($right).' '.$f($l['cy'] - $half).' A'.$f($r).' '.$f($r).' 0 0 1 '.$f($right).' '.$f($l['cy'] + $half);
            $d[] = 'M'.$f($left).' '.$f($l['cy'] - $half).' A'.$f($r).' '.$f($r).' 0 0 0 '.$f($left).' '.$f($l['cy'] + $half);
        }

        return implode(' ', $d);
    }
}
