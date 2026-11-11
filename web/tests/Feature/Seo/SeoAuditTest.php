<?php

declare(strict_types=1);

require_once __DIR__.'/seo-helpers.php';

/*
 * Audit SEO de toutes les pages publiques indexables : balisage de base, hiérarchie H1-H4, Open Graph / Twitter Card,
 * JSON-LD (Schema.org + fil d'Ariane), images (alt + title + dimensions), maillage interne et accessibilité de structure.
 * Une page qui régresse fait échouer le test avec son URL et la règle cassée.
 */

const SEO_PAGES = [
    '/', '/actualites', '/aide', '/conditions-utilisation', '/confidentialite', '/cookies', '/do-glegbe', '/evenements',
    '/lois', '/mentions-legales', '/newsletter', '/politique-de-confidentialite', '/ressources', '/signaler', '/sos',
    '/thematiques', '/urgences', '/violences',
];

dataset('seo-pages', SEO_PAGES);

it('porte les balises de base : langue, viewport, titre, description, canonical, robots', function (string $url): void {
    [$doc, $x] = seoDom($url);

    expect($doc->getElementsByTagName('html')->item(0)->getAttribute('lang'))->toStartWith('fr');
    expect($x->query('//meta[@name="viewport"]')->length)->toBe(1);

    $title = trim($doc->getElementsByTagName('title')->item(0)->textContent);
    expect(mb_strlen($title))->toBeBetween(15, 70, "$url : titre « $title »");

    $desc = (string) seoMeta($x, 'name', 'description');
    expect(mb_strlen($desc))->toBeBetween(70, 160, "$url : description « $desc »");

    $canonical = $x->query('//link[@rel="canonical"]');
    expect($canonical->length)->toBe(1, "$url : un seul canonical");
    expect(rtrim($canonical->item(0)->getAttribute('href'), '/'))->toBe(rtrim(url($url), '/'));

    expect((string) seoMeta($x, 'name', 'robots'))->toContain('index')->not->toContain('noindex');
})->with('seo-pages');

it('a une hiérarchie de titres stricte : un seul H1 et aucun niveau sauté (H1 → H4)', function (string $url): void {
    [, $x] = seoDom($url);

    $h1 = $x->query('//main//h1|//h1');
    expect($h1->length)->toBe(1, "$url : exactement un H1");

    $levels = [];
    foreach ($x->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
        // Titres masqués (modales, offcanvas) : exclus, ils ne structurent pas la page.
        $inHidden = $x->query('ancestor::*[contains(@class,"modal") or contains(@class,"offcanvas") or @hidden or @aria-hidden="true"]', $h)->length > 0;
        if (! $inHidden) {
            $levels[] = (int) substr($h->nodeName, 1);
        }
    }
    expect($levels[0] ?? null)->toBe(1, "$url : le premier titre est le H1");
    for ($i = 1; $i < count($levels); $i++) {
        expect($levels[$i] - $levels[$i - 1])->toBeLessThanOrEqual(1, "$url : saut de niveau H{$levels[$i - 1]} → H{$levels[$i]} (titre n°$i)");
    }
})->with('seo-pages');

it('expose Open Graph et Twitter Card complets et cohérents avec le titre et la description', function (string $url): void {
    [$doc, $x] = seoDom($url);
    $title = trim($doc->getElementsByTagName('title')->item(0)->textContent);

    foreach (['og:site_name', 'og:type', 'og:locale', 'og:title', 'og:description', 'og:url', 'og:image', 'og:image:alt'] as $p) {
        expect(seoMeta($x, 'property', $p))->not->toBeEmpty("$url : $p");
    }
    foreach (['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image', 'twitter:image:alt'] as $n) {
        expect(seoMeta($x, 'name', $n))->not->toBeEmpty("$url : $n");
    }
    expect(seoMeta($x, 'property', 'og:title'))->toBe($title)
        ->and(seoMeta($x, 'name', 'twitter:card'))->toBe('summary_large_image')
        ->and(seoMeta($x, 'property', 'og:image'))->toStartWith('http');
})->with('seo-pages');

it('publie un JSON-LD valide avec Organization, WebSite, WebPage et un BreadcrumbList cohérent', function (string $url): void {
    [, $x] = seoDom($url);

    $blocks = [];
    foreach ($x->query('//script[@type="application/ld+json"]') as $s) {
        $data = json_decode($s->textContent, true, 512, JSON_THROW_ON_ERROR);
        expect($data['@context'] ?? null)->toBe('https://schema.org');
        $blocks[] = $data;
    }
    expect($blocks)->not->toBeEmpty("$url : au moins un bloc JSON-LD");

    $types = collect($blocks)->flatMap(fn (array $b): array => $b['@graph'] ?? [$b])->pluck('@type')->flatten()->all();
    expect($types)->toContain('Organization', 'WebSite', 'WebPage', 'BreadcrumbList');

    $crumb = collect($blocks)->flatMap(fn (array $b): array => $b['@graph'] ?? [$b])->firstWhere('@type', 'BreadcrumbList');
    foreach ($crumb['itemListElement'] as $i => $item) {
        expect($item['position'])->toBe($i + 1)->and($item['name'])->not->toBeEmpty()->and($item['item'])->toStartWith('http');
    }
})->with('seo-pages');

it('donne un alt et un title à chaque image informative, avec dimensions, et masque les décoratives', function (string $url): void {
    [, $x] = seoDom($url);

    foreach ($x->query('//img') as $img) {
        /** @var DOMElement $img */
        $src = $img->getAttribute('src');
        expect($img->hasAttribute('alt'))->toBeTrue("$url : <img src=\"$src\"> sans alt");
        if ($img->getAttribute('alt') === '') {
            expect($img->getAttribute('aria-hidden'))->toBe('true', "$url : image décorative non masquée ($src)");
        } else {
            expect($img->getAttribute('title'))->not->toBeEmpty("$url : <img src=\"$src\"> sans title");
            expect(strlen($img->getAttribute('alt')))->toBeLessThanOrEqual(150, "$url : alt trop long ($src)");
        }
        expect($img->hasAttribute('width') && $img->hasAttribute('height'))->toBeTrue("$url : <img src=\"$src\"> sans width/height");
    }
})->with('seo-pages');

it('a un maillage interne solide : liens avec texte, sans lien vide, un seul main, lien d’évitement', function (string $url): void {
    [, $x, $html] = seoDom($url);

    expect($x->query('//main')->length)->toBe(1, "$url : un seul <main>");
    expect($html)->toContain('xm-skip-link');

    expect($x->query('//a[not(ancestor::template) and (not(@href) or @href="" or starts-with(@href,"javascript:"))]')->length)->toBe(0, "$url : ancre non explorable (sans href exploitable)");

    $internal = 0;
    foreach ($x->query('//a[@href]') as $a) {
        /** @var DOMElement $a */
        $href = $a->getAttribute('href');
        // Lien rendu par Alpine (x-show) : masqué et sans texte tant que le script ne l'a pas rempli, hors de l'arbre d'accessibilité.
        if ($a->hasAttribute('x-show')) {
            continue;
        }
        $name = trim($a->textContent) !== '' || $a->getAttribute('aria-label') !== '' || $a->getAttribute('title') !== ''
            || $x->query('.//img[@alt!=""]', $a)->length > 0;
        expect($name)->toBeTrue("$url : lien sans nom accessible vers $href");
        if (str_starts_with($href, '/') || str_starts_with($href, url('/'))) {
            $internal++;
        }
        if ($a->getAttribute('target') === '_blank') {
            expect($a->getAttribute('rel'))->toContain('noopener', "$url : target=_blank sans noopener ($href)");
        }
    }
    expect($internal)->toBeGreaterThanOrEqual(5, "$url : maillage interne trop pauvre");
})->with('seo-pages');
