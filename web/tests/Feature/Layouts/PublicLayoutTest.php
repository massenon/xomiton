<?php

declare(strict_types=1);

use App\Models\User;

it('affiche la page d’accueil avec le gabarit public', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('<title>'.e(__('seo.pages.home.title')).'</title>', false)
        ->assertSee('class="xm-skip-link"', false)
        ->assertSee('id="pubHeader"', false)
        ->assertSee('<main id="contenu" tabindex="-1">', false)
        ->assertSee('class="xm-pub-footer', false)
        ->assertSee('id="newsletter"', false)
        ->assertDontSee('xm-sos-fab');
});

it('produit les métadonnées de référencement et le JSON-LD', function (): void {
    $this->get('/')
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('name="twitter:card"', false)
        ->assertSee('"@context":"https://schema.org"', false)
        ->assertSee('"BreadcrumbList"', false)
        ->assertDontSee('$__contextArgs');
});

it('indexe les pages publiques pour les visiteur·ses et pas pour les membres', function (): void {
    $this->blade('<x-seo title="Test" />')
        ->assertSee('content="index, follow, max-image-preview:large"', false)
        ->assertSee('<title>Test – Xomiton</title>', false);

    $this->actingAs(User::factory()->create());

    $this->blade('<x-seo title="Test" />')->assertSee('content="noindex, nofollow"', false);
    $this->get('/')->assertSee('xm-sos-fab');
});

it('tronque la description à 160 caractères et garde le nom du site dans le titre', function (): void {
    $this->blade('<x-seo title="Xomiton, accueil" :description="str_repeat(\'mot \', 60)" />')
        ->assertSee('<title>Xomiton, accueil</title>', false)
        ->assertDontSee(str_repeat('mot ', 50));
});

it('sert les pages publiques, sobres et d’authentification', function (string $uri): void {
    expectPublicPage($uri);
})->with(['/thematiques', '/ressources', '/aide', '/urgences', '/sos', '/signaler', '/connexion', '/inscription', '/confidentialite']);

it('redirige les anciennes adresses du prototype', function (): void {
    $this->get('/fil.php')->assertRedirect('/fil')->assertStatus(301);
    $this->get('/landing.php')->assertRedirect('/')->assertStatus(301);
});

it('protège l’espace membre et l’administration', function (): void {
    $this->get('/fil')->assertRedirect('/connexion');
    $this->get('/admin')->assertRedirect('/connexion');
});

it('répond sur la route de santé de l’API', function (): void {
    $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('data.status', 'ok');
});
