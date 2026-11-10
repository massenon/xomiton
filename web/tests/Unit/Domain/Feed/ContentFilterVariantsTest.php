<?php

declare(strict_types=1);

use App\Domain\Feed\ContentFilter;
use App\Domain\Feed\ContentPatterns;
use App\Enums\BadWordSeverity;

/*
 * Filtre de contenus (D-IOS-9) : mots (accents, casse, ponctuation, contournements simples), liste blanche, motifs de
 * coordonnées et de liens. Mots d'essai NEUTRES et inventés : aucun vrai terme injurieux dans les tests.
 */

function termsFor(string ...$words): array
{
    return array_map(fn (string $w): array => ['word' => $w, 'severity' => BadWordSeverity::Block, 'category' => 'insulte'], $words);
}

it('repère un mot malgré les accents, la casse et la ponctuation', function (string $text): void {
    expect((new ContentFilter(termsFor('vilaine')))->check($text)->isBlocked())->toBeTrue();
})->with(['Tu es VILAINE', 'tu es vîlàinë !', 'Vilaine, vilaine.', '« vilaine »', "t'es vilaine?!"]);

it('repère les contournements simples (lettres séparées, répétées, chiffres)', function (string $text): void {
    expect((new ContentFilter(termsFor('vilaine')))->check($text)->isBlocked())->toBeTrue();
})->with(['v.i.l.a.i.n.e', 'v i l a i n e', 'V-I-L-A-I-N-E', 'viiilaine', 'v1l41ne', 'v_i_l_a_i_n_e']);

it('ne se déclenche pas sur un mot plus long ni sur du texte ordinaire', function (string $text): void {
    expect((new ContentFilter(termsFor('vilaine')))->check($text)->isClean())->toBeTrue();
})->with(['la vilainerie du jeu', 'Rendez-vous à 5 h, a b', 'Une très jolie journée', 'a, b, c']);

it('applique la liste blanche aux faux positifs', function (): void {
    $words = termsFor('tarte');

    expect((new ContentFilter($words))->check('Une tarte au citron')->isBlocked())->toBeTrue()
        ->and((new ContentFilter($words, ['Tarte au citron']))->check('Une tarte au citron')->isClean())->toBeTrue()
        ->and((new ContentFilter($words, ['tarte au citron']))->check('Une tarte au citron et une tarte')->isBlocked())->toBeTrue();
});

it('distingue bloquer, masquer et signaler et retient les catégories', function (): void {
    $filter = new ContentFilter([
        ['word' => 'alpha', 'severity' => BadWordSeverity::Block, 'category' => 'haine'],
        ['word' => 'beta', 'severity' => BadWordSeverity::Hide, 'category' => 'insulte'],
        ['word' => 'gamma', 'severity' => BadWordSeverity::Flag, 'category' => 'autre'],
    ]);

    $hide = $filter->check('un beta ici');
    $flag = $filter->check('un gamma ici');
    $all = $filter->check('alpha beta gamma');

    expect($hide->isHidden())->toBeTrue()->and($hide->needsReview())->toBeTrue()->and($hide->isBlocked())->toBeFalse()
        ->and($flag->isFlagged())->toBeTrue()->and($flag->isHidden())->toBeFalse()
        ->and($all->isBlocked())->toBeTrue()->and($all->categories)->toBe(['haine', 'insulte', 'autre']);
});

it('détecte les coordonnées personnelles', function (string $text): void {
    expect((new ContentPatterns)->hasContactDetails($text))->toBeTrue();
})->with([
    'Appelle-moi au +229 97 00 00 00', 'mon numéro : 0197000000', 'tel 97.00.00.00', 'ecris à awa.k@exemple.bj',
    'awa (at) exemple . bj', 'habite au 12 rue des Palmiers', 'maison n° 45 derrière l’école', 'https://wa.me/22997000000',
]);

it('ne prend pas un texte ordinaire pour des coordonnées', function (string $text): void {
    expect((new ContentPatterns)->hasContactDetails($text))->toBeFalse();
})->with(['Rendez-vous le 12/05/2026 à 15 h', 'Nous étions 120 personnes', 'Il y a 3 ans, en 2023', 'Je suis arrivée au carrefour.']);

it('repère les liens suspects et laisse passer le site lui-même', function (): void {
    $patterns = new ContentPatterns(['xomiton.app'], ['bit.ly'], ['zip', 'xyz']);

    expect($patterns->hasSuspiciousLink('Clique http://bit.ly/abc'))->toBeTrue()
        ->and($patterns->hasSuspiciousLink('va sur bit.ly/abc'))->toBeTrue()
        ->and($patterns->hasSuspiciousLink('https://192.168.4.4/login'))->toBeTrue()
        ->and($patterns->hasSuspiciousLink('https://gagner-vite.xyz/offre'))->toBeTrue()
        ->and($patterns->hasSuspiciousLink('https://banque.com@evil.example/'))->toBeTrue()
        ->and($patterns->hasSuspiciousLink('https://xomiton.app/aide'))->toBeFalse()
        ->and($patterns->hasSuspiciousLink('https://www.who.int/fr'))->toBeFalse();
});
