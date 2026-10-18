<?php

declare(strict_types=1);

use App\Application\Seo\SitemapBuilder;
use App\Models\Resource;
use App\Models\Thematique;
use Illuminate\Support\Facades\Cache;

/*
 * sitemap.xml natif (P4-11) : App\Application\Seo\SitemapBuilder + SitemapController.
 */

function sitemapXml(): SimpleXMLElement
{
    $response = test()->get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/xml; charset=UTF-8');

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string((string) $response->getContent());
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    expect($errors)->toBeEmpty()->and($xml)->toBeInstanceOf(SimpleXMLElement::class);

    return $xml;
}

/** @return list<string> */
function sitemapLocs(SimpleXMLElement $xml): array
{
    $locs = [];

    foreach ($xml->url as $url) {
        $locs[] = (string) $url->loc;
    }

    return $locs;
}

it('répond un XML valide contenant l’accueil et les pages publiques statiques', function (): void {
    $locs = sitemapLocs(sitemapXml());

    expect($locs)
        ->toContain(url('/'), route('thematiques.index'), route('ressources.index'), route('urgences'), route('signaler'), route('sos'), route('aide.index'), route('newsletter.show'))
        ->toHaveCount(count(array_unique($locs)));
});

it('ne liste jamais l’administration, l’API, l’espace membre ni les pages secondaires de newsletter', function (): void {
    $locs = sitemapLocs(sitemapXml());

    foreach ($locs as $loc) {
        $path = (string) parse_url($loc, PHP_URL_PATH);

        expect($path)->not->toStartWith('/admin')->not->toStartWith('/api')->not->toStartWith('/fil')
            ->not->toStartWith('/membres')->not->toStartWith('/code-de-verification')->not->toStartWith('/newsletter/');
    }

    // /evenements est encore réservé aux membres (auth) : absent tant que la liste publique n'est pas livrée (Q35).
    expect($locs)->not->toContain(route('evenements.index'));
});

it('inclut les contenus publiés avec lastmod et exclut les brouillons, masqués et programmés', function (): void {
    $published = Thematique::factory()->create(['slug' => 'droits-des-femmes-benin']);
    Thematique::factory()->draft()->create(['slug' => 'brouillon-cache']);
    Thematique::factory()->hidden()->create(['slug' => 'masquee']);
    Thematique::factory()->scheduled()->create(['slug' => 'programmee']);
    $resource = Resource::factory()->create(['slug' => 'guide-securite']);
    $news = Resource::factory()->article()->create(['slug' => 'atelier-porto-novo']);
    Resource::factory()->create(['slug' => 'fiche-brouillon', 'status' => App\Enums\ContentStatus::Draft, 'published_at' => null]);

    $xml = sitemapXml();
    $locs = sitemapLocs($xml);

    expect($locs)
        ->toContain(route('thematiques.show', $published), route('ressources.show', $resource), route('actualites.show', $news))
        ->not->toContain(route('thematiques.show', 'brouillon-cache'), route('thematiques.show', 'masquee'), route('thematiques.show', 'programmee'))
        ->not->toContain(route('ressources.show', 'fiche-brouillon'), route('ressources.show', 'atelier-porto-novo'));

    foreach ($xml->url as $url) {
        if ((string) $url->loc === route('thematiques.show', $published)) {
            expect((string) $url->lastmod)->toBe($published->updated_at->toAtomString());
        }
    }
});

it('échappe les caractères spéciaux et met le XML en cache une heure', function (): void {
    Thematique::factory()->create(['slug' => 'sante-et-droits']);

    $builder = app(SitemapBuilder::class);
    $xml = $builder->render();

    expect($xml)->toContain('<loc>'.route('thematiques.show', 'sante-et-droits').'</loc>')->not->toContain('&amp;amp;');

    Cache::shouldReceive('remember')->once()->withArgs(fn (string $key, int $ttl): bool => $key === SitemapBuilder::CACHE_KEY && $ttl === 3600)->andReturn('<cached/>');

    expect($builder->xml())->toBe('<cached/>');
});
