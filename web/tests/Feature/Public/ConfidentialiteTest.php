<?php

declare(strict_types=1);

use App\Application\Seo\SitemapBuilder;
use App\Domain\Auth\ConsentVersion;

/*
 * Charte, confidentialité et conditions `/confidentialite` (P4-08, prototype privacy-and-terms.php) : quatre sections
 * ancrées (#charte, #confidentialite, #conditions, #signalement) dans l'ordre, sous-navigation collante, date de
 * dernière mise à jour et version des textes, publique et indexable (vous), rouge réservé au bouton « Signaler ».
 */

function legHtml(): string
{
    return (string) test()->get('/confidentialite')->assertOk()->getContent();
}

function legMain(string $html): string
{
    preg_match('~<main id="contenu"[^>]*>(.*)</main>~s', $html, $matches);

    return (string) strstr($matches[1] ?? '', '<div class="offcanvas', true) ?: ($matches[1] ?? '');
}

it('rend les quatre sections ancrées dans l’ordre du prototype, avec leur sous-navigation', function (): void {
    $main = legMain(legHtml());
    preg_match_all('~<section class="card xm-legal-section" id="([a-z]+)"~', $main, $sections);
    preg_match('~<nav class="card xm-legal-nav"[^>]*>(.*?)</nav>~s', $main, $nav);
    preg_match_all('~href="#([a-z]+)"~', $nav[1] ?? '', $links);

    expect($sections[1])->toBe(['charte', 'confidentialite', 'conditions', 'signalement'])
        ->and($links[1])->toBe($sections[1])
        ->and($nav[0] ?? '')->toContain('aria-label="'.e(__('pages/confidentialite.nav_label')).'"')
        ->and($main)->toContain('<h1 class="fw-bold text-white" id="legal-titre">'.e(__('pages/confidentialite.title')).'</h1>')
        ->toContain(e(__('pages/confidentialite.charte.items')[2]))
        ->toContain(e(__('pages/confidentialite.privacy')[1][1]))
        ->toContain('<strong>'.e(__('pages/confidentialite.conditions')[3][0]).'</strong>')
        ->toContain('href="'.route('signaler').'"')->toContain('href="'.route('aide.index').'"');
});

it('affiche la date de dernière mise à jour et la version des consentements', function (): void {
    config(['xomiton.content.legal_updated_at' => '2026-11-02']);

    expect(legMain(legHtml()))->toContain('<time datetime="2026-11-02">2 novembre 2026</time>')
        ->toContain('version '.ConsentVersion::current());
});

it('réserve le rouge au bouton « Signaler » : icônes de titre en primaire', function (): void {
    $main = legMain(legHtml());

    expect(substr_count($main, 'btn btn-danger'))->toBe(1)
        ->and($main)->toContain('<a class="btn btn-danger" href="'.route('signaler').'">')
        ->not->toContain('text-danger');
});

it('écrit les textes en typographie française et en écriture inclusive', function (): void {
    expect(__('pages/confidentialite.charte.intro'))->toEndWith("je m’engage à\u{00A0}:")
        ->and(__('pages/confidentialite.conditions')[0][1])->toContain('allié·es respectueux·ses')
        ->and(__('pages/confidentialite.updated'))->toStartWith("Dernière mise à jour\u{00A0}:");
});

it('produit titre, description, BreadcrumbList, canonical et figure au sitemap', function (): void {
    $html = legHtml();
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $json);
    $crumbs = collect(json_decode($json[1], true)['@graph'])->firstWhere('@type', 'BreadcrumbList');

    expect($html)->toContain('<title>'.e(__('seo.pages.confidentialite.title')).'</title>')
        ->toContain('<link rel="canonical" href="'.route('confidentialite').'">')
        ->toContain('content="index, follow, max-image-preview:large"')
        ->and(array_column($crumbs['itemListElement'], 'name'))->toBe([__('seo.home'), __('pages/confidentialite.title')])
        ->and(array_column(app(SitemapBuilder::class)->urls(), 'loc'))->toContain(rtrim((string) config('app.url'), '/').'/confidentialite');
});

it('sert les ancres annoncées par le pied de page', function (): void {
    $html = legHtml();

    foreach (['charte', 'confidentialite', 'conditions'] as $anchor) {
        expect($html)->toContain('href="'.route('confidentialite').'#'.$anchor.'"')
            ->toContain('id="'.$anchor.'"');
    }
});

it('pose les liens de la sous-navigation directement dans .list-group-flush, comme le prototype', function (): void {
    preg_match('~<nav class="card xm-legal-nav"[^>]*>(.*?)</nav>~s', legMain(legHtml()), $nav);

    expect($nav[1] ?? '')->toContain('<div class="list-group list-group-flush">')
        ->not->toContain('<li')->not->toContain('legalNav')
        ->and(substr_count($nav[1] ?? '', '<a class="list-group-item list-group-item-action" href="#'))->toBe(4);
});

it('décrit exactement les données conservées : IP et navigateur, signalement lié au compte hors anonymat', function (): void {
    [$collected, $report] = [__('pages/confidentialite.privacy')[0][1], __('pages/confidentialite.privacy')[1][1]];

    expect($collected)->toContain('adresse IP')->toContain('consentements')->toContain('journal d’audit')->toContain('appareils connectés')
        ->and($report)->toStartWith('Sans compte, ni identifiant ni adresse IP')
        ->toContain('lié à votre compte et à votre pseudonyme');
});
