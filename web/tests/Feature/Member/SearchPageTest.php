<?php

declare(strict_types=1);

use App\Enums\DirectoryRole;
use App\Http\Middleware\TagGuestResponse;
use App\Models\DirectoryContact;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures as F;
use Tests\Support\PrototypeDirectory;

/*
 * Page « Recherche » `/recherche` (P8-13) : page Inertia `Search/Index` du gabarit membre, résultats sous la forme de
 * l'API (docs/API.md §20), vue d'ensemble ou une famille (`?type=`, première page et pagination), lecture tolérante,
 * noindex et jamais gardée par le service worker, Referer réduit à l'origine, bornes partagées avec l'en-tête
 * (`ui.search`) ; quota `search` de l'API (même compteur, page 429) ; sans compte, connexion ; base en échec, message
 * sans 500.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Resource::factory()->create(['title' => 'Guide Zemidjan prudence']);
    DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone(PrototypeDirectory::zone('Zou'))->create(['name' => 'Centre Zemidjan']);
    $member = User::factory()->withProfile()->create(['pseudo' => 'zemidjan_awa']);
    $member->assignRole('membre');
});

it('sert la vue d’ensemble : familles du compte, premiers résultats, bornes, noindex', function (): void {
    $response = $this->actingAs(F::member())->get('/recherche?q=zemidjan')->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->where('title', 'Recherche')
        ->where('q', 'zemidjan')
        ->where('type', null)
        ->where('types', [
            ['type' => 'ressources', 'label' => 'Ressources', 'icon' => 'bi-folder2-open'],
            ['type' => 'thematiques', 'label' => 'Thématiques', 'icon' => 'bi-journal-text'],
            ['type' => 'contacts', 'label' => 'Contacts', 'icon' => 'bi-person-vcard'],
            ['type' => 'membres', 'label' => 'Membres', 'icon' => 'bi-people'],
        ])
        ->has('sections', 4)
        ->where('sections.0.total', 1)
        ->where('sections.0.items.0.title', 'Guide Zemidjan prudence')
        ->where('sections.1.total', 0)
        ->where('sections.2.items.0.name', 'Centre Zemidjan')
        ->where('sections.3.items.0.pseudo', 'zemidjan_awa')
        ->where('pagination', null)
        ->where('too_short', false)
        ->where('failed', false)
        ->where('limits', ['min_length' => 2, 'max_length' => 80])
        ->where('ui.search', ['min_length' => 2, 'max_length' => 80]));

    $response->assertHeaderMissing(TagGuestResponse::HEADER)->assertHeader('Referrer-Policy', 'strict-origin');
    expect((string) $response->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('sert une famille avec sa pagination et lit l’adresse avec tolérance', function (): void {
    Resource::factory()->count(11)->create(['title' => 'Zemidjan série']);

    $this->actingAs(F::member())->get('/recherche?q=zemidjan&type=ressources&page=abc')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('type', 'ressources')
            ->has('sections', 1)
            ->has('sections.0.items', 10)
            ->where('sections.0.more', true)
            ->where('pagination', ['current_page' => 1, 'last_page' => 2, 'per_page' => 10, 'total' => 12]));

    $this->actingAs(F::member())->get('/recherche?q=%00z%01&type=inconnu')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('q', 'z')->where('type', null)->where('too_short', true)->where('sections.0.total', 0));

    $this->actingAs(F::member())->get('/recherche?q='.str_repeat('a', 120))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('q', str_repeat('a', 80)));
});

it('applique à la page le quota `search` de l’API (même compteur par compte) et affiche la page 429', function (): void {
    config(['xomiton.rate_limits.search_per_minute' => 2]);
    $viewer = F::member();

    $this->actingAs($viewer)->get('/recherche?q=zemidjan')->assertOk();
    $this->actingAs($viewer)->getJson('/api/v1/search/suggestions?q=zemidjan')->assertOk();
    $this->actingAs($viewer)->get('/recherche?q=zemidjan')->assertStatus(429)
        ->assertSee(__('errors.429.title'))
        ->assertHeader('Retry-After')
        ->assertHeader('Referrer-Policy', 'strict-origin');
    $this->actingAs($viewer)->getJson('/api/v1/search?q=zemidjan')->assertStatus(429);

    // Une autre personne garde son propre quota.
    $this->actingAs(F::member())->get('/recherche?q=zemidjan')->assertOk();
    expect(Route::getRoutes()->getByName('recherche')->gatherMiddleware())->toContain('throttle:search');
});

it('renvoie vers la connexion sans compte', function (): void {
    $this->get('/recherche?q=zemidjan')->assertRedirect(route('connexion'));
});

it('affiche un message quand la recherche échoue, sans erreur 500', function (): void {
    Schema::drop('resources');

    $this->actingAs(F::member())->get('/recherche?q=zemidjan')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('failed', true)->where('sections', []));
});
