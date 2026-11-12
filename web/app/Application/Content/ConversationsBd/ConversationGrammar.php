<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Enums\BubbleTailTarget;
use App\Enums\BubbleType;

/**
 * Règles de la grammaire des bulles en code (P20, GRAMMAIRE_DES_BULLES.md §5 et §7 ; PRD P0-1) : un seul validateur
 * pour l'import des modèles, l'administration et, au lot B, l'éditeur des membres (même résultat partout).
 *
 * Erreurs (bloquantes) : 3 à 8 cases ; au moins une bulle dans la conversation ; type inconnu ; texte vide (sauf bulle
 * masquée) ; plus de 20 mots ; plus de 4 bulles par case hors cartouche ; bulle hors de la case (le cri peut déborder de
 * 4 % au plus) ; deux bulles qui se chevauchent sans `superpose` (sauf bulle associée à sa voisine) ; queue incohérente
 * (cartouche avec queue, cible inconnue, queue `personnage` ou `ecran` sans pointe ou hors de la case, pointe dans la
 * bulle) ; bulle associée à une bulle absente ou suivante.
 * Avertissements : ordre de lecture incohérent (bulle lue après une bulle placée plus bas, alors qu'elle est plus haut et
 * plus à gauche) ; plus de 3 bulles superposées dans une case (brouhaha).
 *
 * Les contrôles de contenu des membres (mots interdits, téléphone, e-mail, lien) relèvent du lot B (P20-07).
 */
final class ConversationGrammar
{
    public const MIN_PANELS = 3;

    public const MAX_PANELS = 8;

    public const MAX_WORDS = 20;

    public const MAX_BUBBLES = 4;

    public const SHOUT_OVERFLOW = 4.0;

    /** Tolérance d'arrondi (en %) des contrôles d'encombrement. */
    private const EPSILON = 0.05;

    /**
     * @param  array{panels?: mixed}  $conversation  cases `{bubbles: list<bulle §6>}` dans l'ordre
     * @return list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}>
     */
    public function check(array $conversation): array
    {
        $panels = array_values(array_filter((array) ($conversation['panels'] ?? []), 'is_array'));
        $issues = [];
        $count = count($panels);

        if ($count < self::MIN_PANELS || $count > self::MAX_PANELS) {
            $issues[] = $this->issue('error', 'panel_count', null, null, ['count' => $count, 'min' => self::MIN_PANELS, 'max' => self::MAX_PANELS]);
        }

        $total = 0;

        foreach ($panels as $p => $panel) {
            $bubbles = array_values(array_filter((array) ($panel['bubbles'] ?? []), 'is_array'));
            $total += count($bubbles);
            array_push($issues, ...$this->checkPanel($bubbles, $p + 1));
        }

        if ($total === 0) {
            $issues[] = $this->issue('error', 'no_bubble', null, null);
        }

        return $issues;
    }

    /**
     * @param  list<array{level: string}>  $issues
     * @return list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}>
     */
    public static function errors(array $issues): array
    {
        /** @var list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}> $errors */
        $errors = array_values(array_filter($issues, static fn (array $issue): bool => $issue['level'] === 'error'));

        return $errors;
    }

    /**
     * @param  list<array<string, mixed>>  $bubbles
     * @return list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}>
     */
    public function checkPanel(array $bubbles, int $panel): array
    {
        $issues = [];
        $boxes = [];
        $speaking = 0;
        $overlapping = 0;

        foreach ($bubbles as $i => $bubble) {
            $n = $i + 1;
            $type = BubbleType::tryFrom((string) ($bubble['type'] ?? ''));

            if ($type === null) {
                $issues[] = $this->issue('error', 'type', $panel, $n);

                continue;
            }

            if (! $type->isCaption()) {
                $speaking++;
            }

            if ((bool) ($bubble['superpose'] ?? false)) {
                $overlapping++;
            }

            $masked = (bool) ($bubble['masque'] ?? false);
            $words = count(BubbleGeometry::words((string) ($bubble['texte'] ?? '')));

            if ($words === 0 && ! $masked) {
                $issues[] = $this->issue('error', 'empty', $panel, $n);
            }

            if ($words > self::MAX_WORDS) {
                $issues[] = $this->issue('error', 'too_long', $panel, $n, ['count' => $words, 'max' => self::MAX_WORDS]);
            }

            $box = BubbleGeometry::boxPercent($bubble);
            $boxes[$i] = $box;
            $margin = $type === BubbleType::Shout ? self::SHOUT_OVERFLOW : 0.0;

            if ($box['x'] < -$margin - self::EPSILON || $box['y'] < -$margin - self::EPSILON
                || 100 + $margin + self::EPSILON < $box['x'] + $box['w'] || 100 + $margin + self::EPSILON < $box['y'] + $box['h']) {
                $issues[] = $this->issue('error', 'outside', $panel, $n);
            }

            array_push($issues, ...$this->checkTail($bubble, $type, $box, $bubbles, $i, $panel));

            $link = $bubble['associee_a'] ?? null;
            if ($link !== null && (! is_int($link) || $link < 0 || $link >= $i)) {
                $issues[] = $this->issue('error', 'link', $panel, $n);
            }
        }

        if ($speaking > self::MAX_BUBBLES) {
            $issues[] = $this->issue('error', 'too_many', $panel, null, ['count' => $speaking, 'max' => self::MAX_BUBBLES]);
        }

        if ($overlapping > 3) {
            $issues[] = $this->issue('warning', 'crowd', $panel, null);
        }

        foreach ($boxes as $i => $a) {
            foreach ($boxes as $j => $b) {
                if ($j <= $i || ! $this->intersects($a, $b)) {
                    continue;
                }

                $later = $bubbles[$j];
                $allowed = (bool) ($later['superpose'] ?? false) || ($later['associee_a'] ?? null) === $i;

                if (! $allowed) {
                    $issues[] = $this->issue('error', 'overlap', $panel, $j + 1, ['other' => $i + 1]);
                }
            }
        }

        // Ordre de lecture : de gauche à droite puis de haut en bas (§4). Une bulle lue juste après une autre ne doit pas
        // être entièrement au-dessus et à gauche de celle-ci (sauf superposition voulue).
        for ($j = 1; $j < count($bubbles); $j++) {
            if (! isset($boxes[$j], $boxes[$j - 1]) || (bool) ($bubbles[$j]['superpose'] ?? false)) {
                continue;
            }

            $prev = $boxes[$j - 1];
            $cur = $boxes[$j];

            if ($cur['y'] + $cur['h'] <= $prev['y'] + self::EPSILON && $cur['x'] <= $prev['x']) {
                $issues[] = $this->issue('warning', 'order', $panel, $j + 1);
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $bubble
     * @param  array{x: float, y: float, w: float, h: float}  $box
     * @param  list<array<string, mixed>>  $bubbles
     * @return list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}>
     */
    private function checkTail(array $bubble, BubbleType $type, array $box, array $bubbles, int $index, int $panel): array
    {
        $n = $index + 1;
        $tail = $bubble['queue'] ?? null;

        if ($type->isCaption()) {
            return is_array($tail) ? [$this->issue('error', 'caption_tail', $panel, $n)] : [];
        }

        if (! is_array($tail)) {
            // Seule une bulle suivie dans sa chaîne (bulles associées) peut se passer de queue.
            foreach ($bubbles as $other) {
                if (($other['associee_a'] ?? null) === $index) {
                    return [];
                }
            }

            return [$this->issue('error', 'tail_missing', $panel, $n)];
        }

        $target = BubbleTailTarget::tryFrom((string) ($tail['vers'] ?? ''));

        if ($target === null) {
            return [$this->issue('error', 'tail_target', $panel, $n)];
        }

        $hasX = is_numeric($tail['x'] ?? null);
        $hasY = is_numeric($tail['y'] ?? null);

        if (! $target->isEdge() && (! $hasX || ! $hasY)) {
            return [$this->issue('error', 'tail_point', $panel, $n)];
        }

        $x = $hasX ? (float) $tail['x'] : null;
        $y = $hasY ? (float) $tail['y'] : null;

        if (($x !== null && ($x < 0 || $x > 100)) || ($y !== null && ($y < 0 || $y > 100))) {
            return [$this->issue('error', 'tail_outside', $panel, $n)];
        }

        if (! $target->isEdge() && $x !== null && $y !== null
            && $x > $box['x'] && $x < $box['x'] + $box['w'] && $y > $box['y'] && $y < $box['y'] + $box['h']) {
            return [$this->issue('error', 'tail_inside', $panel, $n)];
        }

        return [];
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $a
     * @param  array{x: float, y: float, w: float, h: float}  $b
     */
    private function intersects(array $a, array $b): bool
    {
        return $a['x'] + self::EPSILON < $b['x'] + $b['w'] && $b['x'] + self::EPSILON < $a['x'] + $a['w']
            && $a['y'] + self::EPSILON < $b['y'] + $b['h'] && $b['y'] + self::EPSILON < $a['y'] + $a['h'];
    }

    /**
     * @param  array<string, int>  $replace
     * @return array{level: string, code: string, panel: int|null, bubble: int|null, message: string}
     */
    private function issue(string $level, string $code, ?int $panel, ?int $bubble, array $replace = []): array
    {
        $message = (string) __('conversations_bd.grammar.'.$code, $replace);

        if ($panel !== null) {
            $where = $bubble !== null
                ? (string) __('conversations_bd.grammar.where_bubble', ['panel' => $panel, 'bubble' => $bubble])
                : (string) __('conversations_bd.grammar.where_panel', ['panel' => $panel]);
            $message = $where.' '.$message;
        }

        return ['level' => $level, 'code' => $code, 'panel' => $panel, 'bubble' => $bubble, 'message' => $message];
    }
}
