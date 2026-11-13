<?php

declare(strict_types=1);

use App\Application\Content\ViolencesNumeriques\ImportViolencesNumeriques;
use App\Application\Seo\SitemapBuilder;
use Tests\Support\ViolencesNumeriquesFixtures;

/*
 * Sujets « à venir » : les 23 sujets sont produits (13/11/2026), mais le comportement reste prévu pour un sujet ajouté
 * plus tard. On importe depuis une copie des données où deux sujets repassent « à venir » : carte sans lien mort sur le
 * carrefour, fiche absente du plan du site.
 */

beforeEach(function (): void {
    ViolencesNumeriquesFixtures::withUpcoming('revenge-porn', 'doxing');
    app(ImportViolencesNumeriques::class)();
});

it('montre les sujets à venir sur le carrefour, sans lien vers une fiche vide', function (): void {
    $html = (string) $this->get('/violences-numeriques')->assertOk()->getContent();

    expect(substr_count($html, 'class="card h-100 xm-vn-topic'))->toBe(23)
        ->and(substr_count($html, 'is-upcoming'))->toBe(2);
    expect($html)->toContain('Revenge porn')
        ->not->toContain(route('thematiques.show', ['slug' => 'revenge-porn']))
        ->not->toContain(route('thematiques.show', ['slug' => 'doxing']))
        ->toContain(route('thematiques.show', ['slug' => 'sextorsion']));
});

it('laisse les sujets à venir hors du plan du site', function (): void {
    config(['app.env' => 'production', 'seo.allow_indexing' => true]);
    SitemapBuilder::flush();

    $locs = collect(app(SitemapBuilder::class)->urls())->pluck('loc');
    $root = rtrim((string) config('app.url'), '/');

    expect($locs)->toContain($root.'/thematiques/sextorsion')
        ->not->toContain($root.'/thematiques/revenge-porn', $root.'/thematiques/doxing', $root.'/bd/vn14', $root.'/bd/vn22');
});
