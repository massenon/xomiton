<?php

declare(strict_types=1);

use App\Application\Seo\SitemapBuilder;
use App\Enums\ContentStatus;
use App\Http\Controllers\Web\PlaceholderController;
use App\Models\Resource;
use App\Models\Thematique;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/*
 * sitemap.xml natif (P4-11) : App\Application\Seo\SitemapBuilder + SitemapController. Servi seulement quand le site
 * accepte l'indexation (production, seo.allow_indexing) ; les écrans provisoires (PlaceholderController, noindex) n'y
 * figurent pas : les tests simulent une page livrée en remplaçant la route par une action définitive.
 */

beforeEach(function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');
});

/**
 * Simule la livraison des pages nommées : même chemin, même nom, mêmes middlewares, action définitive.
 */
function deliverPages(string ...$names): void
{
    foreach ($names as $name) {
        $route = Route::getRoutes()->getByName($name);

        Route::get($route->uri(), static fn (): string => 'page livrée')->name($name)->middleware($route->gatherMiddleware());
    }

    Route::getRoutes()->refreshNameLookups();
}

/** `<loc>` attendu : racine canonique (app.url), jamais l'hôte de la requête. */
function canonical(string $path): string
{
    return rtrim((string) config('app.url'), '/').$path;
}

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

it('répond 404 hors production et quand seo.allow_indexing est à faux', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'testing');
    $this->get('/sitemap.xml')->assertNotFound();

    $this->app->detectEnvironment(static fn (): string => 'production');
    Settings::set('seo.allow_indexing', false);
    $this->get('/sitemap.xml')->assertNotFound();
});

it('ne liste aucun écran provisoire (PlaceholderController, noindex)', function (): void {
    Thematique::factory()->create(['slug' => 'droits-des-femmes-benin']);

    // Livrés : accueil (P4-01), thématiques (P4-02), SOS (P5-05) ; les pages encore servies par PlaceholderController
    // (noindex) n'y figurent jamais. Liste non figée : chaque lot qui livre une page l'y fait entrer.
    $locs = sitemapLocs(sitemapXml());
    expect($locs)->toContain(canonical('/'), canonical('/thematiques'), canonical('/thematiques/droits-des-femmes-benin'), canonical('/sos'));

    foreach (SitemapBuilder::STATIC_ROUTES as $name) {
        $route = Route::getRoutes()->getByName($name);

        if ($route !== null && $route->getControllerClass() === PlaceholderController::class) {
            expect($locs)->not->toContain(route($name));
        }
    }
});

it('répond un XML valide contenant l’accueil et les pages publiques statiques livrées', function (): void {
    deliverPages(...SitemapBuilder::STATIC_ROUTES);
    $locs = sitemapLocs(sitemapXml());

    expect($locs)
        ->toContain(canonical('/'), canonical('/thematiques'), canonical('/ressources'), canonical('/urgences'), canonical('/signaler'), canonical('/sos'), canonical('/aide'), canonical('/newsletter'))
        ->toHaveCount(count(array_unique($locs)));
});

it('ne liste jamais l’administration, l’API, l’espace membre ni les pages secondaires de newsletter', function (): void {
    deliverPages(...SitemapBuilder::STATIC_ROUTES);
    $locs = sitemapLocs(sitemapXml());

    foreach ($locs as $loc) {
        $path = (string) parse_url($loc, PHP_URL_PATH);

        expect($path)->not->toStartWith('/admin')->not->toStartWith('/api')->not->toStartWith('/fil')
            ->not->toStartWith('/membres')->not->toStartWith('/code-de-verification')->not->toStartWith('/newsletter/');
    }

    // P8-04 (Q35 a) : /evenements est public (page Blade des événements publics pour les visiteur·ses) : listé.
    expect($locs)->toContain(canonical('/evenements'));
});

it('inclut les contenus publiés avec lastmod et exclut les brouillons, masqués et programmés', function (): void {
    deliverPages('thematiques.show', 'ressources.show', 'actualites.show');
    $published = Thematique::factory()->create(['slug' => 'droits-des-femmes-benin']);
    Thematique::factory()->draft()->create(['slug' => 'brouillon-cache']);
    Thematique::factory()->hidden()->create(['slug' => 'masquee']);
    Thematique::factory()->scheduled()->create(['slug' => 'programmee']);
    Resource::factory()->create(['slug' => 'guide-securite']);
    Resource::factory()->article()->create(['slug' => 'atelier-porto-novo']);
    Resource::factory()->create(['slug' => 'fiche-brouillon', 'status' => ContentStatus::Draft, 'published_at' => null]);

    $xml = sitemapXml();
    $locs = sitemapLocs($xml);

    expect($locs)
        ->toContain(canonical('/thematiques/droits-des-femmes-benin'), canonical('/ressources/guide-securite'), canonical('/actualites/atelier-porto-novo'))
        ->not->toContain(canonical('/thematiques/brouillon-cache'), canonical('/thematiques/masquee'), canonical('/thematiques/programmee'))
        ->not->toContain(canonical('/ressources/fiche-brouillon'), canonical('/ressources/atelier-porto-novo'));

    foreach ($xml->url as $url) {
        if ((string) $url->loc === canonical('/thematiques/droits-des-femmes-benin')) {
            expect((string) $url->lastmod)->toBe($published->updated_at->toAtomString());
        }
    }
});

it('échappe les caractères spéciaux et met le XML en cache une heure', function (): void {
    deliverPages('thematiques.show');
    Thematique::factory()->create(['slug' => 'sante-et-droits']);

    $builder = app(SitemapBuilder::class);
    $xml = $builder->render();

    expect($xml)->toContain('<loc>'.canonical('/thematiques/sante-et-droits').'</loc>')->not->toContain('&amp;amp;');

    Cache::shouldReceive('remember')->once()->withArgs(fn (string $key, int $ttl): bool => $key === SitemapBuilder::CACHE_KEY && $ttl === 3600)->andReturn('<cached/>');

    expect($builder->xml())->toBe('<cached/>');
});

it('bâtit les loc depuis app.url : un en-tête Host forgé n’empoisonne pas le cache', function (): void {
    deliverPages('home', 'thematiques.index');

    $this->get('http://evil.test/sitemap.xml')->assertOk();

    expect((string) Cache::get(SitemapBuilder::CACHE_KEY))
        ->toContain('<loc>'.canonical('/thematiques').'</loc>')
        ->not->toContain('evil.test');
});

it('sert robots.txt et sitemap.xml sans session ni cookie', function (): void {
    foreach (['/robots.txt', '/sitemap.xml'] as $path) {
        $this->get($path)->assertOk()
            ->assertCookieMissing((string) config('session.cookie'))
            ->assertCookieMissing('XSRF-TOKEN');
    }
});
