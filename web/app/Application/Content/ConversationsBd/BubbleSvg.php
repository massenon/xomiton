<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Enums\BubbleType;

/**
 * Dessin SVG des bulles d'une case (P20-04, GRAMMAIRE_DES_BULLES.md) : rendu serveur pour les pages publiques, le mode
 * séance et, au lot B, l'export PDF. Composant Blade `x-bd.panel` ; jumeau Vue `XmBdPanel` (bubbles.ts) au HTML identique.
 *
 * Bulles associées (§4) : une chaîne est dessinée d'un bloc (contours, puis fonds, puis textes) avec un petit col entre
 * les bulles ; seule la dernière porte la queue. Bulles superposées : l'ordre de dessin suit l'ordre de lecture, la
 * suivante recouvre la précédente. Contour visible = moitié extérieure d'un trait double recouvert par le fond : la
 * queue et le col se fondent dans la bulle sans trait parasite.
 *
 * Le SVG est décoratif pour les lecteurs d'écran (`aria-hidden`) : la case lit sa transcription dans l'ordre (figcaption
 * de `x-bd.panel`), qui annonce aussi le type de chaque bulle (la forme n'est jamais le seul repère). Le texte reste du
 * vrai texte dans le HTML (indexable, sélectionnable).
 */
final class BubbleSvg
{
    /**
     * Chaînes de bulles dans l'ordre de dessin.
     *
     * @param  list<array<string, mixed>>  $bubbles  bulles au format de la grammaire (§6), dans l'ordre de lecture
     * @return list<list<int>> rangs des bulles de chaque chaîne
     */
    public static function chains(array $bubbles): array
    {
        $chainOf = [];
        $chains = [];

        foreach ($bubbles as $index => $bubble) {
            $link = $bubble['associee_a'] ?? null;

            if (is_int($link) && $link >= 0 && $link < $index && isset($chainOf[$link])) {
                $chain = $chainOf[$link];
                $chains[$chain][] = $index;
                $chainOf[$index] = $chain;

                continue;
            }

            $chainOf[$index] = count($chains);
            $chains[] = [$index];
        }

        return $chains;
    }

    /**
     * @param  list<array<string, mixed>>  $bubbles  bulles au format de la grammaire, dans l'ordre de lecture
     */
    public static function render(array $bubbles): string
    {
        $f = BubbleGeometry::fmt(...);
        $out = '<svg class="xm-bdc-svg" viewBox="0 0 '.BubbleGeometry::WIDTH.' '.BubbleGeometry::HEIGHT.'" preserveAspectRatio="xMidYMid meet" overflow="visible" aria-hidden="true" focusable="false">';

        foreach (self::chains($bubbles) as $members) {
            $layouts = [];
            foreach ($members as $index) {
                $layouts[$index] = BubbleGeometry::layout($bubbles[$index]);
            }

            $first = $layouts[$members[0]];
            $isThought = $first['type'] === BubbleType::Thought;
            $last = $members[count($members) - 1];
            $stroke = '';
            $paper = '';
            $decor = '';
            $texts = '';

            // Col entre deux bulles associées : sous les fonds, il ne reste visible qu'entre les bulles.
            for ($i = 1; $i < count($members); $i++) {
                $p = $layouts[$members[$i - 1]];
                $q = $layouts[$members[$i]];
                $coords = 'x1="'.$f($p['cx']).'" y1="'.$f($p['cy']).'" x2="'.$f($q['cx']).'" y2="'.$f($q['cy']).'"';
                $stroke .= '<line '.$coords.' stroke-width="'.$f(18 + 2 * 2.5).'" stroke-linecap="round"/>';
                $paper .= '<line '.$coords.' stroke="'.BubbleGeometry::PAPER.'" stroke-width="18" stroke-linecap="round"/>';
            }

            foreach ($members as $index) {
                $bubble = $bubbles[$index];
                $l = $layouts[$index];
                $type = $l['type'];
                $width = $f(2 * BubbleGeometry::strokeWidth($type));
                $dash = $type === BubbleType::Whisper ? ' stroke-dasharray="12 8"' : '';
                $fill = $l['masked'] ? BubbleGeometry::MASKED_PAPER : ($type === BubbleType::Caption ? BubbleGeometry::CAPTION_PAPER : BubbleGeometry::PAPER);
                $body = BubbleGeometry::bodyPath($l);
                $stroke .= '<path d="'.$body.'" stroke-width="'.$width.'"'.$dash.'/>';
                $paper .= '<path d="'.$body.'" fill="'.$fill.'"/>';

                $tip = $index === $last ? BubbleGeometry::tip($bubble, $l) : null;
                if ($tip !== null) {
                    $tail = BubbleGeometry::tail($l, $tip);
                    if ($tail['path'] !== null) {
                        $stroke .= '<path d="'.$tail['path'].'" stroke-width="'.$width.'"'.$dash.'/>';
                        $paper .= '<path d="'.$tail['path'].'" fill="'.$fill.'"/>';
                    }
                    foreach ($tail['circles'] as $c) {
                        $circle = 'cx="'.$f($c['cx']).'" cy="'.$f($c['cy']).'" r="'.$f($c['r']).'"';
                        $stroke .= '<circle '.$circle.' stroke-width="'.$width.'"/>';
                        $paper .= '<circle '.$circle.' fill="'.$fill.'"/>';
                    }
                }

                if ($type === BubbleType::DeviceVoice) {
                    $decor .= '<path class="xm-bdc-waves" d="'.BubbleGeometry::wavesPath($l).'" fill="none" stroke="'.BubbleGeometry::INK.'" stroke-width="2.5" stroke-linecap="round"/>';
                }

                if ($l['masked']) {
                    foreach ([[0.8, -0.32], [0.62, 0.32]] as [$ratio, $offset]) {
                        $barW = $l['textW'] * $ratio;
                        $paper .= '<rect x="'.$f($l['cx'] - $barW / 2).'" y="'.$f($l['cy'] + $l['textH'] * $offset - 6).'" width="'.$f($barW).'" height="12" rx="6" fill="'.BubbleGeometry::MASKED_BAR.'"/>';
                    }
                }

                $texts .= self::text($l);
            }

            $class = 'xm-bdc-chain'.($isThought ? ' xm-bdc-thought' : '');
            $out .= '<g class="'.$class.'" data-bubble-type="'.$first['type']->value.'">'
                .'<g fill="none" stroke="'.BubbleGeometry::INK.'" stroke-linejoin="round">'.$stroke.'</g>'
                .'<g stroke="none">'.$paper.'</g>'
                .$decor.$texts
                .'</g>';
        }

        return $out.'</svg>';
    }

    /**
     * @param  array{type: BubbleType, font: float, lines: list<list<array{text: string, bold: bool}>>, textH: float, cx: float, cy: float}  $l
     */
    private static function text(array $l): string
    {
        if ($l['lines'] === []) {
            return '';
        }

        $f = BubbleGeometry::fmt(...);
        $type = $l['type'];
        $strong = $type === BubbleType::Shout || $type === BubbleType::Caption;
        $attributes = 'x="'.$f($l['cx']).'" font-size="'.$f($l['font']).'" font-family="'.e($strong ? BubbleGeometry::FONT_STRONG : BubbleGeometry::FONT_TEXT).'" text-anchor="middle" fill="'.BubbleGeometry::INK.'"';

        if ($strong) {
            $attributes .= ' font-weight="700"';
        }

        if ($type === BubbleType::Thought || $type === BubbleType::DeviceVoice) {
            $attributes .= ' font-style="italic"';
        }

        $lineHeight = $l['font'] * BubbleGeometry::LINE_HEIGHT;
        $top = $l['cy'] - $l['textH'] / 2;
        $out = '<text '.$attributes.'>';

        foreach ($l['lines'] as $i => $line) {
            $baseline = $top + ($i + 0.5) * $lineHeight + 0.35 * $l['font'];
            $out .= '<tspan x="'.$f($l['cx']).'" y="'.$f($baseline).'">'.self::segments($line, $strong).'</tspan>';
        }

        return $out.'</text>';
    }

    /**
     * Mots consécutifs de même graisse regroupés ; une emphase devient un tspan gras (le cri et le cartouche sont déjà gras).
     *
     * @param  list<array{text: string, bold: bool}>  $line
     */
    private static function segments(array $line, bool $strong): string
    {
        $hasBold = ! $strong && in_array(true, array_column($line, 'bold'), true);

        if (! $hasBold) {
            return e(implode(' ', array_column($line, 'text')));
        }

        $groups = [];
        foreach ($line as $word) {
            $lastIndex = count($groups) - 1;
            if ($lastIndex >= 0 && $groups[$lastIndex]['bold'] === $word['bold']) {
                $groups[$lastIndex]['words'][] = $word['text'];
            } else {
                $groups[] = ['bold' => $word['bold'], 'words' => [$word['text']]];
            }
        }

        $out = '';
        foreach ($groups as $i => $group) {
            $text = implode(' ', $group['words']).($i < count($groups) - 1 ? ' ' : '');
            $out .= '<tspan'.($group['bold'] ? ' font-weight="700"' : '').'>'.e($text).'</tspan>';
        }

        return $out;
    }
}
