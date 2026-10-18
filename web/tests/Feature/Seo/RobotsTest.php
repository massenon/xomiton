<?php

declare(strict_types=1);

use App\Support\Settings;

/*
 * robots.txt dynamique (P4-11) : App\Http\Controllers\Web\RobotsController.
 */

it('interdit toute exploration hors production', function (): void {
    $response = $this->get('/robots.txt')->assertOk();

    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('Cache-Control', 'max-age=86400, public');

    expect($response->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

it('autorise l’index en production avec les exclusions et le sitemap', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');

    $content = (string) $this->get('/robots.txt')->assertOk()->getContent();

    expect($content)
        ->toStartWith("User-agent: *\n")
        ->toContain('Disallow: /admin', 'Disallow: /api', 'Disallow: /storage', 'Disallow: /newsletter/confirmer', 'Disallow: /newsletter/desinscription', 'Disallow: /otp', 'Disallow: /code-de-verification', 'Disallow: /recherche')
        ->toContain('Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml')
        ->not->toContain("Disallow: /\n");
});

it('interdit toute exploration en recette', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'staging');

    expect((string) $this->get('/robots.txt')->assertOk()->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

it('interdit tout en production si le paramètre seo.allow_indexing est à faux', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');
    Settings::set('seo.allow_indexing', false);

    expect((string) $this->get('/robots.txt')->assertOk()->getContent())->toBe("User-agent: *\nDisallow: /\n");

    Settings::set('seo.allow_indexing', true);
    config()->set('xomiton.seo.allow_indexing', false);

    // Le paramètre en base prime sur la valeur de configuration.
    expect((string) $this->get('/robots.txt')->getContent())->toContain('Sitemap:');
});

it('n’a plus de fichier public/robots.txt statique', function (): void {
    expect(file_exists(public_path('robots.txt')))->toBeFalse();
});
