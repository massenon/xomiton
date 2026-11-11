<?php

declare(strict_types=1);

use App\Support\HeadingOutline;

/** @return list<string> les niveaux de titres, dans l'ordre */
function outlineLevels(string $html): array
{
    preg_match_all('/<h([1-6])\b/', $html, $m);

    return $m[1];
}

it('ramène le premier intertitre à un h2 et supprime les sauts de niveau', function (): void {
    $out = HeadingOutline::normalize('<h4>Intro</h4><p>a</p><h2>Suite</h2><h4>Détail</h4><h3>Sous</h3>');

    expect(outlineLevels($out))->toBe(['2', '2', '3', '3']);
});

it('laisse remonter librement et ne touche pas une hiérarchie déjà correcte', function (): void {
    $ok = '<h2>A</h2><h3>B</h3><h4>C</h4><h2>D</h2><h3>E</h3>';

    expect(outlineLevels(HeadingOutline::normalize($ok)))->toBe(['2', '3', '4', '2', '3']);
    expect(outlineLevels(HeadingOutline::normalize('<h2>A</h2><h4>B</h4><h2>C</h2>')))->toBe(['2', '3', '2']);
});

it('conserve le texte, les accents, les liens et les attributs', function (): void {
    $out = HeadingOutline::normalize('<h4 id="x">Éléments d’aide &amp; <a href="https://exemple.bj/a?b=1&amp;c=2">liens</a></h4><p class="lead">Ça reste « intact ».</p>');

    expect($out)->toContain('<h2 id="x">Éléments d’aide &amp; <a href="https://exemple.bj/a?b=1&amp;c=2">liens</a></h2>')
        ->toContain('<p class="lead">Ça reste « intact ».</p>');
});

it('ne change rien sans intertitre et accepte le vide', function (): void {
    expect(HeadingOutline::normalize('<p>Texte seul</p>'))->toBe('<p>Texte seul</p>')
        ->and(HeadingOutline::normalize(null))->toBe('')
        ->and(HeadingOutline::normalize(''))->toBe('');
});
