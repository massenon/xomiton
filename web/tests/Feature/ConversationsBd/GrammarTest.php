<?php

declare(strict_types=1);

use App\Application\Content\ConversationsBd\ConversationGrammar;

if (! function_exists('bdcNb')) {
    /** Typographie des fichiers de lang et des données : espace insécable avant « : ! ? ». */
    function bdcNb(string $text): string
    {
        return str_replace([' :', ' !', ' ?'], ["\u{00A0}:", "\u{00A0}!", "\u{00A0}?"], $text);
    }
}

/*
 * P20 : règles de la grammaire des bulles en code (GRAMMAIRE_DES_BULLES.md §5, §7 ; ConversationGrammar, partagé par
 * l'import, l'administration et l'éditeur des membres du lot B).
 */

function bdcBubble(array $over = []): array
{
    return array_replace(['type' => 'parole', 'personnage' => 'afi', 'texte' => 'Bonjour.', 'emphase' => [], 'position' => ['x' => 4, 'y' => 4], 'queue' => ['vers' => 'personnage', 'x' => 20, 'y' => 70], 'associee_a' => null, 'superpose' => false, 'masque' => false], $over);
}

/** @param list<array<string, mixed>> $bubbles */
function bdcCodes(array $bubbles, int $panels = 3): array
{
    $list = [['bubbles' => $bubbles]];
    for ($i = 1; $i < $panels; $i++) {
        $list[] = ['bubbles' => []];
    }

    return array_column((new ConversationGrammar)->check(['panels' => $list]), 'code');
}

it('accepte une conversation conforme', function (): void {
    expect(bdcCodes([bdcBubble()]))->toBe([]);
});

it('exige de 3 à 8 cases et au moins une bulle', function (): void {
    expect(bdcCodes([bdcBubble()], 2))->toContain('panel_count')
        ->and(bdcCodes([bdcBubble()], 9))->toContain('panel_count')
        ->and(bdcCodes([], 3))->toContain('no_bubble');
});

it('refuse plus de 20 mots, une bulle vide (sauf masquée) et un type inconnu', function (): void {
    expect(bdcCodes([bdcBubble(['texte' => trim(str_repeat('mot ', 21))])]))->toContain('too_long')
        ->and(bdcCodes([bdcBubble(['texte' => trim(str_repeat('mot ', 20))])]))->not->toContain('too_long')
        ->and(bdcCodes([bdcBubble(['texte' => '  '])]))->toContain('empty')
        ->and(bdcCodes([bdcBubble(['type' => 'message', 'texte' => '', 'masque' => true, 'queue' => ['vers' => 'ecran', 'x' => 50, 'y' => 90]])]))->toBe([])
        ->and(bdcCodes([bdcBubble(['type' => 'bulle'])]))->toContain('type');
});

it('compte au plus 4 bulles par case hors cartouche', function (): void {
    $row = fn (int $i): array => bdcBubble(['position' => ['x' => 2 + $i * 24, 'y' => 4], 'queue' => ['vers' => 'personnage', 'x' => 10 + $i * 24, 'y' => 80]]);
    $four = [bdcBubble(['type' => 'cartouche', 'personnage' => null, 'texte' => 'Le soir.', 'position' => ['x' => 1, 'y' => 60], 'queue' => null]), $row(0), $row(1), $row(2), $row(3)];

    expect(bdcCodes($four))->toBe([])
        ->and(bdcCodes([...$four, bdcBubble(['position' => ['x' => 40, 'y' => 40], 'queue' => ['vers' => 'personnage', 'x' => 50, 'y' => 90]])]))->toContain('too_many');
});

it('garde la bulle dans la case, le cri pouvant déborder de 4 % au plus', function (): void {
    expect(bdcCodes([bdcBubble(['position' => ['x' => 95, 'y' => 4]])]))->toContain('outside')
        ->and(bdcCodes([bdcBubble(['position' => ['x' => -1, 'y' => 4]])]))->toContain('outside')
        ->and(bdcCodes([bdcBubble(['type' => 'cri', 'texte' => 'Non !', 'position' => ['x' => -3, 'y' => -2]])]))->not->toContain('outside')
        ->and(bdcCodes([bdcBubble(['type' => 'cri', 'texte' => 'Non !', 'position' => ['x' => -5, 'y' => 4]])]))->toContain('outside');
});

it('refuse un chevauchement sauf bulle superposée ou associée', function (): void {
    $a = bdcBubble();
    $b = bdcBubble(['position' => ['x' => 8, 'y' => 6], 'queue' => ['vers' => 'personnage', 'x' => 60, 'y' => 80]]);

    expect(bdcCodes([$a, $b]))->toContain('overlap')
        ->and(bdcCodes([$a, [...$b, 'superpose' => true]]))->not->toContain('overlap')
        ->and(bdcCodes([[...$a, 'queue' => null], [...$b, 'associee_a' => 0]]))->toBe([]);
});

it('vérifie la queue : cartouche sans queue, cible connue, pointe dans la case et hors de la bulle', function (): void {
    expect(bdcCodes([bdcBubble(['type' => 'cartouche', 'queue' => ['vers' => 'personnage', 'x' => 10, 'y' => 80]])]))->toContain('caption_tail')
        ->and(bdcCodes([bdcBubble(['queue' => null])]))->toContain('tail_missing')
        ->and(bdcCodes([bdcBubble(['queue' => ['vers' => 'ciel']])]))->toContain('tail_target')
        ->and(bdcCodes([bdcBubble(['queue' => ['vers' => 'personnage']])]))->toContain('tail_point')
        ->and(bdcCodes([bdcBubble(['queue' => ['vers' => 'personnage', 'x' => 120, 'y' => 50]])]))->toContain('tail_outside')
        ->and(bdcCodes([bdcBubble(['queue' => ['vers' => 'personnage', 'x' => 8, 'y' => 7]])]))->toContain('tail_inside')
        ->and(bdcCodes([bdcBubble(['queue' => ['vers' => 'bord-droit']])]))->toBe([]);
});

it('refuse une bulle associée à une bulle suivante ou absente', function (): void {
    expect(bdcCodes([bdcBubble(['associee_a' => 0])]))->toContain('link')
        ->and(bdcCodes([bdcBubble(['associee_a' => 3])]))->toContain('link');
});

it('signale un ordre de lecture incohérent et un brouhaha, sans bloquer', function (): void {
    $low = bdcBubble(['position' => ['x' => 50, 'y' => 60], 'queue' => ['vers' => 'personnage', 'x' => 60, 'y' => 95]]);
    $high = bdcBubble(['position' => ['x' => 4, 'y' => 4], 'queue' => ['vers' => 'bord-gauche']]);
    $issues = (new ConversationGrammar)->check(['panels' => [['bubbles' => [$low, $high]], ['bubbles' => []], ['bubbles' => []]]]);

    expect(array_column($issues, 'code'))->toBe(['order'])
        ->and($issues[0]['level'])->toBe('warning')
        ->and(ConversationGrammar::errors($issues))->toBe([])
        ->and($issues[0]['message'])->toStartWith(bdcNb('Case 1, bulle 2 :'));
});
