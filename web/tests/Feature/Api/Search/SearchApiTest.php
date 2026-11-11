<?php

declare(strict_types=1);

use App\Enums\DirectoryRole;
use App\Enums\ProfileVisibility;
use App\Enums\ResourceKind;
use App\Enums\UserStatus;
use App\Models\DirectoryContact;
use App\Models\Resource;
use App\Models\Thematique;
use App\Models\User;
use App\Models\UserBlock;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Support\FeedFixtures as F;
use Tests\Support\PrototypeDirectory;

/*
 * Recherche membre (P8-13, docs/API.md §20) : `GET search` (vue d'ensemble par famille, une famille paginée avec `type`)
 * et `GET search/suggestions` (forme courte de l'en-tête). Colonnes en clair seulement (ADR-33), membres trouvables
 * seulement (réglage P8-09, blocages, profils fermés), contacts selon la portée de l'annuaire, contenus publiés
 * seulement, `no-store`, quota `search` hors `api`, erreur de base rendue 503 sans le terme ; familles fermées de bout
 * en bout (sans `social.access` : pas de membres ; sans `directory.view` : pas de contacts), superadmin jamais trouvé.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    $this->viewer = F::member();
    $this->zou = PrototypeDirectory::zone('Zou');

    $this->resource = Resource::factory()->ofKind(ResourceKind::Guide)->create(['title' => 'Guide Zemidjan prudence', 'published_at' => now()->subDay()]);
    Resource::factory()->create(['title' => 'Autre fiche', 'summary' => 'Trajets en zemidjan la nuit', 'published_at' => now()->subDays(2)]);
    Resource::factory()->draft()->create(['title' => 'Zemidjan brouillon']);
    Resource::factory()->hidden()->create(['title' => 'Zemidjan masquée']);
    Resource::factory()->article()->create(['title' => 'Zemidjan actualité']);
    $this->thematique = Thematique::factory()->create(['title' => 'Se déplacer', 'summary' => 'Conseils pour le zemidjan']);
    Thematique::factory()->draft()->create(['title' => 'Zemidjan en préparation']);

    $this->contact = DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($this->zou)->create(['name' => 'Centre Zemidjan', 'notes' => 'zemidjan interne']);
    $this->reserved = DirectoryContact::factory()->role(DirectoryRole::PairAnimateur)->inZone($this->zou)->hidden()->create(['name' => 'Relais Zemidjan']);
    DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($this->zou)->inactive()->create(['name' => 'Zemidjan inactif']);
    DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($this->zou)->create(['name' => 'Cabinet Akpé', 'notes' => 'Zemidjan dans les notes chiffrées']);

    $this->findable = searchMember('zemidjan_awa');
    $this->hiddenMember = searchMember('zemidjan_discrete', ['searchable' => false]);
    $this->privateMember = searchMember('zemidjan_privee', ['visibility' => ProfileVisibility::Private]);
    $this->blocked = searchMember('zemidjan_bloquee');
    UserBlock::query()->create(['user_id' => $this->blocked->id, 'blocked_user_id' => $this->viewer->id]);
    $this->suspended = searchMember('zemidjan_suspendue');
    $this->suspended->forceFill(['status' => UserStatus::Suspended])->save();
    $this->realName = searchMember('awa_kpodo', [], ['name' => 'Awa Zemidjan']);
});

function searchMember(string $pseudo, array $profile = [], array $attributes = []): User
{
    $user = User::factory()->withProfile($profile)->create(['pseudo' => $pseudo] + $attributes);
    $user->assignRole('membre');

    return $user;
}

/** @return array<string, list<string>> titres par famille de la vue d'ensemble */
function searchTitles(Illuminate\Testing\TestResponse $response): array
{
    $titles = [];
    foreach ($response->json('data') as $section) {
        $titles[$section['type']] = array_map(static fn (array $item): string => (string) ($item['title'] ?? $item['name'] ?? $item['pseudo']), $section['items']);
    }

    return $titles;
}

it('cherche ressources, thématiques, contacts et membres trouvables, en no-store et sans renvoyer le terme', function (): void {
    $response = F::as($this->viewer)->getJson('/api/v1/search?q=zemidjan')->assertOk();

    expect(array_column($response->json('data'), 'type'))->toBe(['ressources', 'thematiques', 'contacts', 'membres'])
        ->and(searchTitles($response))->toBe([
            'ressources' => ['Guide Zemidjan prudence', 'Autre fiche'],
            'thematiques' => ['Se déplacer'],
            'contacts' => ['Centre Zemidjan'],
            'membres' => ['zemidjan_awa'],
        ])
        ->and($response->json('data.0'))->toMatchArray(['label' => 'Ressources', 'icon' => 'bi-folder2-open', 'total' => 2, 'more' => false])
        ->and($response->json('data.0.items.0'))->toMatchArray([
            'type' => 'ressources',
            'id' => $this->resource->id,
            'kind' => 'guide',
            'url' => route('ressources.show', $this->resource),
        ])
        ->and($response->json('data.1.items.0.url'))->toBe(route('thematiques.show', $this->thematique))
        ->and($response->json('data.2.items.0'))->toMatchArray(['type' => 'contacts', 'id' => $this->contact->id, 'is_public' => true, 'url' => route('annuaire', ['q' => 'Centre Zemidjan'])])
        ->and($response->json('data.2.items.0'))->not->toHaveKeys(['notes', 'user_id'])
        ->and($response->json('data.3.items.0'))->toMatchArray(['type' => 'membres', 'id' => $this->findable->id, 'profile_url' => route('membres.show', ['zemidjan_awa'])])
        ->and($response->json('data.3.items.0'))->not->toHaveKeys(['name', 'email', 'phone'])
        ->and($response->json('meta.too_short'))->toBeFalse()
        ->and(array_column($response->json('meta.types'), 'type'))->toBe(['ressources', 'thematiques', 'contacts', 'membres'])
        ->and($response->json())->not->toHaveKey('links')
        ->and(json_encode($response->json('meta')))->not->toContain('zemidjan')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('n’affiche jamais un membre non trouvable, privé, bloqué, suspendu ni soi-même, et ne cherche jamais le nom réel', function (): void {
    $viewer = searchMember('zemidjan_moi');
    $members = F::as($viewer)->getJson('/api/v1/search?q=zemidjan&type=membres')->assertOk()->json('data');

    expect(array_column($members, 'pseudo'))->toBe(['zemidjan_awa', 'zemidjan_bloquee'])
        ->and(F::as($this->viewer)->getJson('/api/v1/search?q=zemidjan&type=membres')->json('data.*.pseudo'))->toBe(['zemidjan_awa', 'zemidjan_moi'])
        ->and(F::as($this->viewer)->getJson('/api/v1/search?q=Kpodo&type=membres')->json('meta.total'))->toBe(1)
        ->and(F::as($this->viewer)->getJson('/api/v1/search?q=Awa%20Zemidjan&type=membres')->json('meta.total'))->toBe(0);
});

it('ne cherche jamais dans une colonne chiffrée ni dans une fiche non publique d’un·e membre', function (): void {
    $contacts = F::as($this->viewer)->getJson('/api/v1/search?q=zemidjan&type=contacts')->assertOk();

    expect($contacts->json('data.*.name'))->toBe(['Centre Zemidjan'])
        ->and(F::as($this->viewer)->getJson('/api/v1/search?q=interne&type=contacts')->json('meta.total'))->toBe(0);
});

it('ouvre aux pair·es validé·es les fiches réservées au réseau, comme l’annuaire', function (): void {
    $peer = F::member('pair');
    $peer->forceFill(['status' => UserStatus::Active])->save();

    $names = F::as($peer)->getJson('/api/v1/search?q=zemidjan&type=contacts')->assertOk()->json('data.*.name');

    expect($names)->toBe(['Centre Zemidjan', 'Relais Zemidjan']);
});

it('pagine une famille avec `type` et revient à la vue d’ensemble pour un type inconnu', function (): void {
    Resource::factory()->count(11)->create(['title' => 'Zemidjan série', 'published_at' => now()->subMonth()]);

    $first = F::as($this->viewer)->getJson('/api/v1/search?q=zemidjan&type=ressources')->assertOk();
    $second = F::as($this->viewer)->getJson('/api/v1/search?q=zemidjan&type=ressources&page=2')->assertOk();

    expect($first->json('meta'))->toMatchArray(['type' => 'ressources', 'current_page' => 1, 'last_page' => 2, 'per_page' => 10, 'total' => 13])
        ->and($first->json('data'))->toHaveCount(10)
        ->and($second->json('data'))->toHaveCount(3)
        ->and(array_intersect($first->json('data.*.id'), $second->json('data.*.id')))->toBe([])
        ->and(F::as($this->viewer)->getJson('/api/v1/search?q=zemidjan&type=inconnu')->json('data.0.type'))->toBe('ressources');
});

it('ne lance aucune requête pour un terme trop court et l’annonce', function (): void {
    DB::enableQueryLog();
    $response = F::as($this->viewer)->getJson('/api/v1/search?q=z')->assertOk();
    $likes = collect(DB::getQueryLog())->filter(static fn (array $query): bool => str_contains(strtolower($query['query']), ' like '));

    expect($response->json('meta.too_short'))->toBeTrue()
        ->and(array_sum(array_column($response->json('data'), 'total')))->toBe(0)
        ->and($likes)->toBeEmpty()
        ->and(F::as($this->viewer)->getJson('/api/v1/search')->json('meta.too_short'))->toBeFalse();
});

it('sert des suggestions courtes, trois par famille au plus, sans famille vide', function (): void {
    Resource::factory()->count(4)->create(['title' => 'Zemidjan série']);

    $response = F::as($this->viewer)->getJson('/api/v1/search/suggestions?q=zemidjan')->assertOk();
    $resources = $response->json('data.0');

    expect(array_column($response->json('data'), 'type'))->toBe(['ressources', 'thematiques', 'contacts', 'membres'])
        ->and($resources['items'])->toHaveCount(3)
        ->and($resources['total'])->toBe(6)
        ->and($resources['more'])->toBeTrue()
        ->and(array_keys($resources['items'][0]))->toBe(['type', 'id', 'title', 'meta', 'url', 'inertia', 'icon', 'avatar_url'])
        ->and($resources['items'][0]['inertia'])->toBeFalse()
        ->and($response->json('data.2.items.0'))->toMatchArray(['title' => 'Centre Zemidjan', 'meta' => 'Juriste · Zou', 'inertia' => true])
        ->and($response->json('data.3.items.0'))->toMatchArray(['title' => 'zemidjan_awa', 'inertia' => true, 'url' => route('membres.show', ['zemidjan_awa'])])
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(F::as($this->viewer)->getJson('/api/v1/search/suggestions?q=Akpé')->json('data.*.type'))->toBe(['contacts']);
});

it('n’ouvre les membres qu’avec `social.access`, les contacts qu’avec `directory.view`, et ne trouve jamais un compte superadmin', function (): void {
    searchMember('zemidjan_super')->syncRoles(['superadmin']);
    $types = static fn (Illuminate\Testing\TestResponse $response): array => array_column($response->json('data'), 'type');

    expect(searchTitles(F::as($this->viewer)->getJson('/api/v1/search?q=zemidjan'))['membres'])->toBe(['zemidjan_awa'])
        ->and(F::as($this->viewer)->getJson('/api/v1/search?type=membres&q=zemidjan_s')->assertOk()->json('meta.total'))->toBe(0);

    Role::findByName('membre', 'web')->revokePermissionTo('social.access');
    $viewer = $this->viewer->fresh();
    $overview = F::as($viewer)->getJson('/api/v1/search?q=zemidjan')->assertOk();

    expect($types($overview))->toBe(['ressources', 'thematiques', 'contacts'])
        ->and(array_column($overview->json('meta.types'), 'type'))->toBe(['ressources', 'thematiques', 'contacts'])
        // Famille fermée demandée : vue d'ensemble, jamais la liste des membres.
        ->and($types(F::as($viewer)->getJson('/api/v1/search?type=membres&q=zemidjan')->assertOk()))->toBe(['ressources', 'thematiques', 'contacts'])
        ->and($types(F::as($viewer)->getJson('/api/v1/search/suggestions?q=zemidjan')->assertOk()))->not->toContain('membres');

    Role::findByName('membre', 'web')->revokePermissionTo('directory.view');
    $viewer = $viewer->fresh();

    expect($types(F::as($viewer)->getJson('/api/v1/search?q=zemidjan')->assertOk()))->toBe(['ressources', 'thematiques'])
        ->and($types(F::as($viewer)->getJson('/api/v1/search?type=contacts&q=zemidjan')->assertOk()))->toBe(['ressources', 'thematiques'])
        ->and($types(F::as($viewer)->getJson('/api/v1/search/suggestions?q=Centre')->assertOk()))->toBe([]);
});

it('exige un compte et applique le quota `search`, hors du quota `api`', function (): void {
    $this->getJson('/api/v1/search?q=zemidjan')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');

    config(['xomiton.rate_limits.api_per_minute' => 1, 'xomiton.rate_limits.search_per_minute' => 2]);
    $token = F::token($this->viewer);

    $this->withToken($token)->getJson('/api/v1/search/suggestions?q=zemidjan')->assertOk();
    $this->withToken($token)->getJson('/api/v1/search?q=zemidjan')->assertOk();
    $this->withToken($token)->getJson('/api/v1/search?q=zemidjan')->assertStatus(429);

    $route = Route::getRoutes()->getByName('api.search.index');
    expect($route->excludedMiddleware())->toContain('throttle:api')
        ->and($route->gatherMiddleware())->toContain('throttle:search');
});

it('répond 503 search_unavailable sans le terme quand la base échoue', function (): void {
    Schema::drop('resources');

    F::as($this->viewer)->getJson('/api/v1/search?q=zemidjan')
        ->assertStatus(503)
        ->assertJsonPath('code', 'search_unavailable')
        ->assertJsonPath('message', __('search.errors.unavailable'));
});
