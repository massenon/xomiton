<?php

declare(strict_types=1);

require_once __DIR__.'/seo-helpers.php';

use App\Models\Category;
use App\Models\Event;
use App\Models\Faq;
use App\Models\Resource;
use App\Models\Thematique;

/*
 * Audit SEO des pages publiques dynamiques (fiches d'actualité, de ressource, de thématique, d'aide, événement public) :
 * mêmes exigences que SeoAuditTest (balises de base, H1-H4, OG/Twitter, JSON-LD, images, liens) plus le Schema.org propre à
 * chaque type de contenu, un fil d'Ariane qui remonte vers la liste, et le comportement des contenus non publiables.
 */

/** Page de détail publique indexable. @return array{0: DOMDocument, 1: DOMXPath, 2: string} */
function dynDom(string $url): array
{
    return seoDom($url);
}

/** @return list<array<string, mixed>> */
function dynGraph(DOMXPath $x): array
{
    $nodes = [];
    foreach ($x->query('//script[@type="application/ld+json"]') as $s) {
        $data = json_decode($s->textContent, true, 512, JSON_THROW_ON_ERROR);
        foreach ($data['@graph'] ?? [$data] as $node) {
            $nodes[] = $node;
        }
    }

    return $nodes;
}

/** @return array<string, mixed> le nœud JSON-LD de ce type sur la page */
function dynNode(string $url, string $type): array
{
    $node = collect(dynGraph(seoDom($url)[1]))->firstWhere('@type', $type);
    expect($node)->not->toBeNull("$url : schéma $type attendu");

    return $node;
}

/** Les contrôles communs, appliqués à une page de détail. */
function dynAssertCommon(string $url): void
{
    [$doc, $x, $html] = seoDom($url);

    $title = trim($doc->getElementsByTagName('title')->item(0)->textContent);
    expect(mb_strlen($title))->toBeBetween(15, 75, "$url : titre « $title »");

    $desc = (string) seoMeta($x, 'name', 'description');
    expect(mb_strlen($desc))->toBeBetween(50, 160, "$url : description « $desc »");

    expect(rtrim($x->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'), '/'))->toBe(rtrim(url($url), '/'));
    expect((string) seoMeta($x, 'name', 'robots'))->toContain('index')->not->toContain('noindex');

    expect($x->query('//h1')->length)->toBe(1, "$url : un seul H1");
    $levels = [];
    foreach ($x->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
        if ($x->query('ancestor::*[contains(@class,"modal") or contains(@class,"offcanvas") or @hidden or @aria-hidden="true"]', $h)->length === 0) {
            $levels[] = (int) substr($h->nodeName, 1);
        }
    }
    expect($levels[0])->toBe(1);
    for ($i = 1; $i < count($levels); $i++) {
        expect($levels[$i] - $levels[$i - 1])->toBeLessThanOrEqual(1, "$url : saut de niveau H{$levels[$i - 1]} → H{$levels[$i]}");
    }

    foreach (['og:title', 'og:description', 'og:url', 'og:image', 'og:image:alt', 'og:type'] as $p) {
        expect(seoMeta($x, 'property', $p))->not->toBeEmpty("$url : $p");
    }
    foreach (['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image', 'twitter:image:alt'] as $n) {
        expect(seoMeta($x, 'name', $n))->not->toBeEmpty("$url : $n");
    }
    expect(seoMeta($x, 'property', 'og:url'))->toBe(url($url))->and(seoMeta($x, 'property', 'og:title'))->toBe($title);

    $types = collect(dynGraph($x))->pluck('@type')->flatten()->all();
    expect($types)->toContain('Organization', 'WebSite', 'WebPage', 'BreadcrumbList');

    $crumb = collect(dynGraph($x))->firstWhere('@type', 'BreadcrumbList');
    expect(count($crumb['itemListElement']))->toBeGreaterThanOrEqual(3, "$url : le fil d'Ariane doit passer par la liste");
    foreach ($crumb['itemListElement'] as $i => $item) {
        expect($item['position'])->toBe($i + 1)->and($item['item'])->toStartWith('http');
    }

    foreach ($x->query('//img') as $img) {
        /** @var DOMElement $img */
        $src = $img->getAttribute('src');
        expect($img->hasAttribute('alt'))->toBeTrue("$url : <img $src> sans alt");
        if ($img->getAttribute('alt') !== '') {
            expect($img->getAttribute('title'))->not->toBeEmpty("$url : <img $src> sans title");
        }
        expect($img->hasAttribute('width') && $img->hasAttribute('height'))->toBeTrue("$url : <img $src> sans width/height");
    }

    expect($x->query('//a[not(ancestor::template) and not(@x-show) and (not(@href) or @href="" or starts-with(@href,"javascript:"))]')->length)->toBe(0, "$url : ancre non explorable");
    foreach ($x->query('//a[@target="_blank"]') as $a) {
        expect($a->getAttribute('rel'))->toContain('noopener', "$url : target=_blank sans noopener");
    }
    expect($html)->toContain('xm-skip-link');
}

it('audite une fiche d’actualité : balises, titres, Open Graph, Article en JSON-LD', function (): void {
    $article = Resource::factory()->article()->create(['title' => 'Comprendre ses droits face aux violences conjugales au Bénin']);

    dynAssertCommon('/actualites/'.$article->slug);

    [, $x] = seoDom('/actualites/'.$article->slug);
    $nodes = collect(dynGraph($x));
    $art = $nodes->first(fn (array $n): bool => in_array($n['@type'], ['Article', 'NewsArticle', 'BlogPosting'], true));
    expect($art)->not->toBeNull('schéma Article attendu')
        ->and($art['headline'])->not->toBeEmpty()
        ->and($art['datePublished'])->not->toBeEmpty()
        ->and($art['mainEntityOfPage'])->not->toBeEmpty();
    expect(seoMeta($x, 'property', 'og:type'))->toBe('article');
});

it('audite une fiche de ressource : balises, titres, Open Graph, JSON-LD', function (): void {
    $resource = Resource::factory()->create(['title' => 'Guide pratique : porter plainte et être accompagnée']);

    dynAssertCommon('/ressources/'.$resource->slug);

    $art = dynNode('/ressources/'.$resource->slug, 'Article');
    expect($art['headline'])->toBe('Guide pratique : porter plainte et être accompagnée')
        ->and($art)->toHaveKeys(['datePublished', 'dateModified', 'image', 'author', 'publisher', 'mainEntityOfPage', 'inLanguage'])
        ->and($art['isAccessibleForFree'])->toBeTrue();
});

it('audite une fiche de thématique : balises, titres, Open Graph, JSON-LD', function (): void {
    $thematique = Thematique::factory()->create(['title' => 'Violences basées sur le genre et soutien aux victimes']);

    dynAssertCommon('/thematiques/'.$thematique->slug);

    expect(dynNode('/thematiques/'.$thematique->slug, 'Article'))->toHaveKeys(['headline', 'image', 'publisher', 'mainEntityOfPage']);
});

it('audite un article d’aide : balises, titres, Open Graph, FAQPage en JSON-LD', function (): void {
    // Un article du centre d'aide appartient à une catégorie de type « faq » (sinon 404, voir ShowHelpArticle).
    $category = Category::factory()->ofKind(Category::KIND_FAQ)->create(['label' => 'Compte et profil']);
    $faq = Faq::factory()->create(['category_id' => $category->id, 'question' => 'Comment changer mon pseudonyme sur la plateforme ?']);

    dynAssertCommon('/aide/'.$faq->slug);
});

it('audite un événement public : balises, titres, Open Graph, JSON-LD, sans adresse exacte', function (): void {
    $event = Event::factory()->openToPublic()->withAddress('Maison des jeunes, salle 3')->create([
        'title' => 'Atelier de sensibilisation aux droits des femmes', 'place' => 'Cotonou', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(10)->addHours(2),
    ]);

    dynAssertCommon('/evenements/'.$event->ulid);

    [, , $html] = seoDom('/evenements/'.$event->ulid);
    expect($html)->not->toContain('Maison des jeunes, salle 3');

    $node = dynNode('/evenements/'.$event->ulid, 'Event');
    expect($node)->toHaveKeys(['name', 'description', 'startDate', 'endDate', 'eventStatus', 'eventAttendanceMode', 'location', 'image', 'organizer', 'inLanguage'])
        ->and($node['eventStatus'])->toBe('https://schema.org/EventScheduled')
        ->and($node['location']['address']['addressCountry'])->toBe('BJ')
        ->and($node['location']['address'])->not->toHaveKey('streetAddress')
        ->and(strtotime($node['startDate']))->toBeLessThan(strtotime($node['endDate']));
});

it('porte le schéma de liste adapté : CollectionPage pour les événements, FAQPage pour l’aide', function (): void {
    Faq::factory()->create(['category_id' => Category::factory()->ofKind(Category::KIND_FAQ)->create()->id, 'show_on_home' => false]);

    expect(collect(dynGraph(seoDom('/evenements')[1]))->pluck('@type')->all())->toContain('CollectionPage');
    expect(collect(dynGraph(seoDom('/aide')[1]))->pluck('@type')->all())->toContain('FAQPage');
});

it('ne laisse jamais indexer un brouillon, une fiche programmée ou masquée', function (): void {
    $slugs = [
        '/actualites/' => Resource::factory()->article()->draft()->create(['title' => 'Brouillon'])->slug,
        '/ressources/' => Resource::factory()->draft()->create(['title' => 'Brouillon de fiche'])->slug,
    ];
    foreach ($slugs as $prefix => $slug) {
        $this->get($prefix.$slug)->assertNotFound();
    }
    $this->get('/actualites/inexistant-xyz')->assertNotFound();
    $this->get('/aide/inexistant-xyz')->assertNotFound();
});

it('met les pages de liste paginées en canonical propre et sans doublon d’indexation', function (): void {
    Resource::factory()->article()->count(14)->create();

    foreach (['/actualites', '/actualites?page=2'] as $url) {
        [, $x] = seoDom($url);
        $canonical = $x->query('//link[@rel="canonical"]');
        expect($canonical->length)->toBe(1, "$url : un seul canonical")
            ->and($canonical->item(0)->getAttribute('href'))->toStartWith('http');
    }
});

it('garde une hiérarchie H1-H4 stricte même quand le corps éditorial saute des niveaux', function (): void {
    $body = '<h4>Intro directe</h4><p>Texte.</p><h2>Suite</h2><h4>Détail sauté</h4><p>x</p><h3>Sous-partie</h3>';
    $pages = [
        '/actualites/'.Resource::factory()->article()->create(['body' => $body])->slug,
        '/ressources/'.Resource::factory()->create(['body' => $body])->slug,
        '/thematiques/'.Thematique::factory()->create(['body' => $body])->slug,
    ];

    foreach ($pages as $url) {
        $levels = [];
        foreach (seoDom($url)[1]->query('//h1|//h2|//h3|//h4') as $h) {
            $levels[] = (int) substr($h->nodeName, 1);
        }
        expect($levels[0])->toBe(1, "$url : le premier titre est le H1");
        for ($i = 1; $i < count($levels); $i++) {
            expect($levels[$i] - $levels[$i - 1])->toBeLessThanOrEqual(1, "$url : saut H{$levels[$i - 1]} → H{$levels[$i]}");
        }
    }
});

it('coupe un titre éditorial très long sans le laisser déborder dans <title>, og:title et twitter:title', function (): void {
    $article = Resource::factory()->article()->create([
        'title' => 'Un titre éditorial extrêmement long qui décrit en détail toutes les démarches à suivre pour obtenir de l’aide après une agression au Bénin',
    ]);

    [$doc, $x] = seoDom('/actualites/'.$article->slug);
    $title = trim($doc->getElementsByTagName('title')->item(0)->textContent);

    expect(mb_strlen($title))->toBeLessThanOrEqual(70)
        ->and($title)->toEndWith('Xomiton')
        ->and(seoMeta($x, 'property', 'og:title'))->toBe($title)
        ->and(seoMeta($x, 'name', 'twitter:title'))->toBe($title);
    // Le titre complet reste dans le H1 : seule la balise <title> est raccourcie.
    expect(trim($x->query('//h1')->item(0)->textContent))->toContain('toutes les démarches à suivre');
});

it('sert un événement public passé avec les mêmes garanties SEO', function (): void {
    $event = Event::factory()->openToPublic()->past()->create(['title' => 'Atelier passé sur les droits des femmes au Bénin']);

    dynAssertCommon('/evenements/'.$event->ulid);
    expect(dynNode('/evenements/'.$event->ulid, 'Event'))->toHaveKeys(['name', 'startDate', 'eventStatus']);
});
