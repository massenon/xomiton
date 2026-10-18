<?php

declare(strict_types=1);

use App\Enums\GroupRole;
use App\Models\Event;
use App\Models\Group;
use App\Models\Page;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommunityFixtures as C;
use Tests\Support\FeedFixtures as F;

/*
 * Écrans Vue des groupes et des pages (P8-02, P8-03 ; prototype groups.php, group-details.php, pages.php,
 * create-page.php) : pages Inertia `Community/*` (plus l'écran provisoire), premières pages sous la forme de l'API,
 * groupe privé d'une non-membre en fiche réduite sans publications ni membres (FR-SOC-05), page non approuvée
 * introuvable pour les autres (P12-05), composer de groupe (`group`, audience « Membres du groupe »).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = F::member();
    $this->bea = F::member();
});

it('sert /groupes : onglet, recherche, cartes et réglages de la modale de création', function (): void {
    C::group($this->awa, ['name' => 'Cercle public']);
    C::group($this->awa, ['name' => 'Cercle secret', 'privacy' => 'prive']);

    $this->actingAs($this->bea)->get('/groupes?tab=inconnu&q=Cercle')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community/Groups')
            ->where('title', 'Groupes')
            ->where('tab', 'discover')
            ->where('q', 'Cercle')
            ->has('groups.data', 1)
            ->where('groups.data.0.name', 'Cercle public')
            ->where('groups.meta.last_page', 1)
            ->where('create.can', true)
            ->where('create.privacies.0.value', 'prive')
            ->where('create.limits.name_min', 3)
            ->missing('prototype'));
});

it('sert la fiche d’un groupe : membre avec publications, membres, événements et composer de groupe', function (): void {
    $group = C::group($this->awa);
    C::addMember($group, $this->bea);
    C::postIn($this->awa, $group, 'Rendez-vous samedi')->assertCreated();
    Event::factory()->create(['group_id' => $group->id, 'title' => 'Causerie du groupe', 'starts_at' => now()->addDays(3)]);

    $this->actingAs($this->bea)->get('/groupes/'.$group->ulid.'?tab=posts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community/GroupShow')
            ->where('title', $group->name)
            ->where('tab', 'posts')
            ->where('group.id', $group->ulid)
            ->where('group.restricted', false)
            ->where('group.membership.status', 'actif')
            ->where('posts.data.0.body', 'Rendez-vous samedi')
            ->where('posts.data.0.can.comment', true)
            ->has('members.data', 2)
            ->where('members.data.0.is_owner', true)
            ->where('events.0.title', 'Causerie du groupe')
            ->where('composer.group', $group->ulid)
            ->where('composer.visibilities', [['value' => 'public', 'label' => 'Membres du groupe']]));
});

it('réduit la fiche d’un groupe privé pour une non-membre et rend 404 pour un groupe inactif', function (): void {
    $group = C::group($this->awa, ['privacy' => 'prive', 'description' => 'Réservé aux membres']);
    C::postIn($this->awa, $group, 'Message réservé')->assertCreated();

    $this->actingAs($this->bea)->get('/groupes/'.$group->ulid.'?tab=posts')
        ->assertOk()
        ->assertDontSee('Message réservé')
        ->assertDontSee('Réservé aux membres')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community/GroupShow')
            ->where('tab', 'about')
            ->where('group.restricted', true)
            ->where('group.description', null)
            ->where('posts', null)
            ->where('members', null)
            ->where('events', [])
            ->where('composer', null));

    $inactive = Group::factory()->inactive()->create();
    $this->actingAs($this->bea)->get('/groupes/'.$inactive->ulid)->assertNotFound();
    $this->actingAs($this->bea)->get('/groupes/12')->assertNotFound();
});

it('montre les demandes en attente à l’équipe du groupe', function (): void {
    $group = C::group($this->awa, ['privacy' => 'prive']);
    $moderator = F::member();
    C::addMember($group, $moderator, GroupRole::Moderator);
    C::join($this->bea, $group)->assertOk();

    $this->actingAs($moderator)->get('/groupes/'.$group->ulid.'?tab=members')
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'members')->where('group.pending_requests', 1)->where('group.can.moderate', true));
});

it('sert /pages, /pages/creer et la fiche ; page en attente introuvable pour les autres', function (): void {
    Page::factory()->create(['name' => 'Clinique amie', 'category' => 'Santé']);
    $pending = Page::factory()->pending()->create(['name' => 'Réseau en attente', 'owner_id' => $this->awa->id]);

    $this->actingAs($this->bea)->get('/pages?categorie=Santé')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community/Pages')
            ->where('tab', 'all')
            ->where('category', 'Santé')
            ->where('categories', ['Santé'])
            ->has('pages.data', 1)
            ->where('pages.data.0.name', 'Clinique amie')
            ->where('can_create', true));

    $this->actingAs($this->bea)->get('/pages/creer')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community/PageCreate')
            ->where('title', 'Créer une page')
            ->where('categories', ['Organisation', 'Santé', 'Juridique', 'Réseau', 'Éducation', 'Autonomisation'])
            ->where('limits.description_max', 400));

    $this->actingAs($this->bea)->get('/pages/'.$pending->ulid)->assertNotFound();
    app('auth')->forgetGuards(); // garde Sanctum (lu par les Resources) mémorisé d'une requête à l'autre du même test
    $this->actingAs($this->awa)->get('/pages/'.$pending->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community/PageShow')
            ->where('organization.status', 'pending')
            ->where('organization.is_mine', true));
    $this->actingAs($this->awa)->get('/pages?tab=mine')->assertInertia(fn (Assert $page) => $page->has('pages.data', 1));
});

it('sert /groupes et /pages sans aucune liste à un compte sans accès au réseau (social.access)', function (): void {
    C::group($this->awa, ['name' => 'Cercle public']);
    Page::factory()->create(['name' => 'Clinique de la Paix']);
    $this->bea->removeRole('membre');

    $this->actingAs($this->bea)->get('/groupes')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Community/Groups')->has('groups.data', 0)->where('create.can', false));
    $this->actingAs($this->bea)->get('/pages')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Community/Pages')->has('pages.data', 0)->where('can_create', false));
});
