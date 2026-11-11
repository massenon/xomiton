<?php

declare(strict_types=1);

use App\Enums\GroupPrivacy;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Group;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures as F;

/*
 * `/evenements`, `/evenements/{ulid}` (P8-04, Q35 a) : une adresse, deux publics. Membre : pages Inertia `Events/*`
 * (premières pages sous la forme de l'API, adresse exacte seulement après « Je participe », groupe privé → 404) ;
 * visiteur·se : page Blade publique indexable des seuls événements `is_public` (ville, jamais l'adresse ni le lien,
 * JSON-LD Event), événement réservé ou inconnu → connexion ; plan du site.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = F::member();
});

it('sert /evenements aux membres : onglet, catégories, cartes sans adresse et réglages de la modale', function (): void {
    $atelier = Category::factory()->create(['kind' => Category::KIND_EVENT, 'slug' => 'atelier', 'label' => 'Atelier', 'is_active' => true]);
    Event::factory()->inCategory($atelier)->withAddress()->create(['title' => 'Atelier « Dire non »', 'starts_at' => now()->addDays(2)]);
    Event::factory()->forGroup(Group::factory()->create(['privacy' => GroupPrivacy::Private]))->create(['title' => 'Cercle privé', 'starts_at' => now()->addDay()]);

    $this->actingAs($this->awa)->get('/evenements?filter=inconnu&cat=atelier')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->where('title', 'Événements')
            ->where('filter', 'upcoming')
            ->where('category', 'atelier')
            ->where('categories', [['slug' => 'atelier', 'label' => 'Atelier']])
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Atelier « Dire non »')
            ->where('events.data.0.exact_place_reserved', true)
            ->missing('events.data.0.address')
            ->where('create.can', true)
            ->where('create.can_publish_public', false)
            ->where('create.limits.title_min', 3)
            ->missing('prototype'));

    expect($this->get('/evenements')->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('sert la fiche membre : sans adresse avant « Je participe », avec après ; groupe privé et inconnu → 404', function (): void {
    $event = Event::factory()->withAddress('Centre des femmes, porte 2')->create(['title' => 'Causerie', 'place' => 'Parakou', 'starts_at' => now()->addDays(3)]);
    Event::factory()->create(['title' => 'Autre rendez-vous', 'starts_at' => now()->addDays(5)]);

    $this->actingAs($this->awa)->get('/evenements/'.$event->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('title', 'Causerie')
            ->where('event.id', $event->ulid)
            ->where('event.place', 'Parakou')
            ->missing('event.address')
            ->has('others', 1)
            ->where('others.0.title', 'Autre rendez-vous'));

    EventRsvp::factory()->create(['event_id' => $event->id, 'user_id' => $this->awa->id, 'status' => 'going']);
    $this->get('/evenements/'.$event->ulid)
        ->assertInertia(fn (Assert $page) => $page->where('event.address', 'Centre des femmes, porte 2')->where('event.rsvp.status', 'going'));

    $secret = Event::factory()->forGroup(Group::factory()->create(['privacy' => GroupPrivacy::Private]))->create();
    $this->get('/evenements/'.$secret->ulid)->assertNotFound();
    $this->get('/evenements/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
});

it('sert aux visiteur·ses la liste publique indexable des seuls événements publics, sans adresse', function (): void {
    Event::factory()->openToPublic()->withAddress('Maison des jeunes, salle 3')->create([
        'title' => 'Journée de dépistage gratuit', 'place' => 'Cotonou', 'is_online' => true, 'online_url' => 'https://visio.example.org/z', 'starts_at' => now()->addDays(4),
    ]);
    Event::factory()->create(['title' => 'Cercle réservé aux membres', 'starts_at' => now()->addDays(2)]);
    Event::factory()->openToPublic()->forGroup(Group::factory()->create(['privacy' => GroupPrivacy::Private]))->create(['title' => 'Public mais groupe privé', 'starts_at' => now()->addDays(2)]);

    $response = $this->get('/evenements')->assertOk()->assertViewIs('public.evenements.index');
    $html = (string) $response->getContent();

    expect($html)->toContain('Journée de dépistage gratuit')->toContain('Cotonou · en ligne')
        ->not->toContain('Cercle réservé aux membres')->not->toContain('Public mais groupe privé')
        ->not->toContain('Maison des jeunes')->not->toContain('visio.example.org')
        ->toContain('"@type":"CollectionPage"')
        ->and($response->headers->get('X-Robots-Tag'))->toBeNull()
        ->and($html)->toContain('index, follow');

    $this->get('/evenements?cat=Mal_Forme')->assertNotFound();
    $this->get('/evenements?cat=inconnue')->assertNotFound();
    // Visite Inertia sans session (session expirée) : rechargement complet vers la page publique.
    $this->get('/evenements', ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location');
});

it('sert la fiche publique d’un événement public (JSON-LD Event, ville seulement) et renvoie le reste vers la connexion', function (): void {
    $public = Event::factory()->openToPublic()->withAddress('Maison des jeunes, salle 3')->create([
        'title' => 'Atelier ouvert', 'place' => 'Porto-Novo', 'description' => 'Exercices de mise en situation.', 'starts_at' => now()->addDays(6),
    ]);
    $members = Event::factory()->create(['starts_at' => now()->addDays(2)]);

    $html = (string) $this->get('/evenements/'.$public->ulid)->assertOk()->assertViewIs('public.evenements.show')->getContent();
    expect($html)->toContain('Atelier ouvert')->toContain('Porto-Novo')->toContain('"@type":"Event"')
        ->toContain('"addressLocality":"Porto-Novo"')->not->toContain('Maison des jeunes')
        // « Je participe » et « Se connecter » ramènent sur la fiche après la connexion (`next`).
        ->toContain(e(route('connexion', ['next' => '/evenements/'.$public->ulid])));

    // Événement réservé ou inconnu : même réponse (connexion avec retour sur la fiche).
    $this->get('/evenements/'.$members->ulid)->assertRedirect(route('connexion', ['next' => '/evenements/'.$members->ulid]));
    $this->get('/evenements/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertRedirect(route('connexion', ['next' => '/evenements/01JZZZZZZZZZZZZZZZZZZZZZZZ']));
});

it('ne publie jamais le pseudo de l’organisatrice sur la page publique indexée : page, groupe ou « Équipe Xomiton »', function (): void {
    $organizer = F::member();
    $solo = Event::factory()->openToPublic()->create(['organizer_id' => $organizer->id, 'starts_at' => now()->addDays(3)]);
    $label = Event::factory()->openToPublic()->organizedBy('Prénom Nom')->create(['starts_at' => now()->addDays(3)]);
    $group = Group::factory()->create(['privacy' => GroupPrivacy::Public, 'name' => 'Cercle des sœurs']);
    $inGroup = Event::factory()->openToPublic()->forGroup($group)->create(['organizer_id' => $organizer->id, 'starts_at' => now()->addDays(4)]);
    $page = Page::factory()->create(['name' => 'Association Akwaba']);
    $onPage = Event::factory()->openToPublic()->forPage($page)->create(['organizer_id' => $organizer->id, 'starts_at' => now()->addDays(5)]);

    foreach ([[$solo, 'Organisé par Équipe Xomiton'], [$label, 'Organisé par Équipe Xomiton'], [$inGroup, 'Organisé par Cercle des sœurs'], [$onPage, 'Organisé par Association Akwaba']] as [$event, $expected]) {
        $html = (string) $this->get('/evenements/'.$event->ulid)->assertOk()->getContent();
        expect($html)->toContain(e($expected))->not->toContain((string) $organizer->pseudo)->not->toContain('Prénom Nom');
    }

    // Les membres gardent le pseudo (page Vue, noindex).
    $this->actingAs(F::member())->get('/evenements/'.$solo->ulid)->assertInertia(fn (Assert $p) => $p->where('event.organizer', $organizer->pseudo));
});

it('n’affiche pas les événements d’une page en attente ou désactivée (liste, fiche, public, plan du site)', function (): void {
    $pending = Event::factory()->openToPublic()->forPage(Page::factory()->pending()->create())->create(['title' => 'Page en attente', 'starts_at' => now()->addDays(2)]);
    $inactive = Event::factory()->openToPublic()->forPage(Page::factory()->inactive()->create())->create(['title' => 'Page désactivée', 'starts_at' => now()->addDays(2)]);
    Event::factory()->openToPublic()->create(['title' => 'Atelier visible', 'starts_at' => now()->addDays(3)]);

    $this->actingAs($this->awa)->get('/evenements')
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.title', 'Atelier visible'));
    $this->get('/evenements/'.$pending->ulid)->assertNotFound();
    $this->get('/evenements/'.$inactive->ulid)->assertNotFound();

    auth()->logout();
    $html = (string) $this->get('/evenements')->assertOk()->getContent();
    expect($html)->toContain('Atelier visible')->not->toContain('Page en attente')->not->toContain('Page désactivée');
    $this->get('/evenements/'.$pending->ulid)->assertRedirect();
    $this->get('/evenements/'.$inactive->ulid)->assertRedirect();

    $this->app->detectEnvironment(static fn (): string => 'production');
    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->not->toContain($pending->ulid)->not->toContain($inactive->ulid);
});

it('liste /evenements et les événements publics à venir dans le plan du site, jamais les autres', function (): void {
    $public = Event::factory()->openToPublic()->create(['starts_at' => now()->addDays(3)]);
    $members = Event::factory()->create(['starts_at' => now()->addDays(3)]);
    $past = Event::factory()->openToPublic()->past()->create();
    $this->app->detectEnvironment(static fn (): string => 'production');

    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
    $root = rtrim((string) config('app.url'), '/');

    expect($xml)->toContain($root.'/evenements</loc>')
        ->toContain($root.'/evenements/'.$public->ulid)
        ->not->toContain($members->ulid)
        ->not->toContain($past->ulid);
});

it('sert la page publique à un compte connecté sans accès au réseau social', function (): void {
    $members = Event::factory()->create(['title' => 'Cercle réservé aux membres', 'starts_at' => now()->addDay()]);
    $public = Event::factory()->openToPublic()->create(['title' => 'Journée de dépistage', 'starts_at' => now()->addDays(2)]);
    $user = User::factory()->withProfile()->create();

    $html = (string) $this->actingAs($user)->get('/evenements')->assertOk()->assertViewIs('public.evenements.index')->getContent();
    expect($html)->toContain('Journée de dépistage')->not->toContain('Cercle réservé aux membres')
        ->not->toContain(__('pages/evenements.join_login'));
    $this->get('/evenements/'.$public->ulid)->assertOk()->assertViewIs('public.evenements.show');
    $this->get('/evenements/'.$members->ulid)->assertNotFound();
    // Visite Inertia (lien du gabarit membre) : rechargement complet plutôt qu'un HTML dans la fenêtre Inertia.
    $this->get('/evenements', ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location');
});
