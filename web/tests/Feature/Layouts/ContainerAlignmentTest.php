<?php

declare(strict_types=1);

// Un seul gabarit horizontal (D-ALIGN-1) : en-tête, corps et pied de page publics utilisent la même classe `.container`,
// dont la largeur maximale et la gouttière viennent des jetons --xm-container-max et --xm-gutter (CSS critique et principal).

it('utilise la même classe de conteneur dans l’en-tête, le corps et le pied de page', function () {
    $header = file_get_contents(resource_path('views/partials/public-header.blade.php'));
    $footer = file_get_contents(resource_path('views/partials/public-footer.blade.php'));

    expect($header)->toContain('<div class="container">')
        ->and($footer)->toContain('<div class="container">');

    $html = $this->get('/ressources')->assertOk()->getContent();
    expect($html)->toMatch('~<main[^>]*>.*?class="container[ "]~s');
});

it('définit les jetons de conteneur et leur règle partagée dans les deux feuilles', function (string $file) {
    $css = file_get_contents(resource_path($file));

    expect($css)->toContain('--xm-container-max:')
        ->and($css)->toContain('--xm-gutter:')
        ->and($css)->toContain(':is(header, main, footer) .container')
        ->and($css)->toContain('max-width: var(--xm-container-max)')
        ->and($css)->toContain('--bs-gutter-x: var(--xm-gutter)');
})->with(['css/critical.css', 'css/theme/xomiton.css']);

it('ne modifie pas le thème Social', function () {
    expect(file_get_contents(resource_path('css/theme/style.css')))->not->toContain('--xm-container-max');
});
