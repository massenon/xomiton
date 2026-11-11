<?php

declare(strict_types=1);

use App\Enums\DirectoryRole;
use App\Enums\UserStatus;
use App\Http\Middleware\TagGuestResponse;
use App\Models\Commune;
use App\Models\DirectoryContact;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures as F;
use Tests\Support\PrototypeDirectory;

/*
 * Écran Vue « Annuaire dynamique » `/annuaire` (P8-12 ; prototype annuaire.php) : page Inertia `Member/Directory`, fiches
 * de la portée du compte sous la forme de l'API (docs/API.md §19), filtres lus avec tolérance, carte par département,
 * bornes de la modale de rappel. Compatibilité hors ligne (P5-10) : page d'une session jamais marquée
 * `X-Xm-Audience: guest` (le service worker ne la garde pas) ; sans compte, redirection vers la connexion (le
 * préchargement sans cookie la refuse, repli `/urgences`).
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    $this->zou = PrototypeDirectory::zone('Zou');
    $this->commune = Commune::query()->where('zone_id', $this->zou->id)->orderBy('position')->firstOrFail();
    DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($this->zou, $this->commune)->create(['name' => 'ADJOVI Rose', 'notes' => 'Note interne']);
    DirectoryContact::factory()->role(DirectoryRole::PairAnimateur)->inZone($this->zou)->hidden()->create(['name' => 'DOSSOU Inès']);
});

it('sert /annuaire : fiches publiques, carte, filtres, rôles, départements et bornes de la modale', function (): void {
    $response = $this->actingAs(F::member())->get('/annuaire?zone='.$this->zou->id.'&commune='.$this->commune->id)->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Member/Directory')
        ->where('title', 'Annuaire')
        ->where('reach', 'public')
        ->where('filters', ['q' => null, 'role' => null, 'zone' => $this->zou->id, 'commune' => $this->commune->id])
        ->has('roles', count(DirectoryRole::cases()))
        ->where('roles.0', ['value' => 'point_focal', 'label' => 'Point focal'])
        ->has('zones', 12)
        ->has('zones.0.communes')
        ->has('contacts.data', 1)
        ->where('contacts.data.0.name', 'ADJOVI Rose')
        ->where('contacts.data.0.commune.id', $this->commune->id)
        ->where('contacts.data.0.can.callback', true)
        ->missing('contacts.data.0.notes')
        ->missing('contacts.data.0.user_id')
        ->where('contacts.meta', ['current_page' => 1, 'last_page' => 1, 'per_page' => 24, 'total' => 1])
        ->has('map.zones', 12)
        ->where('map.national', 0)
        ->where('map.total', 1)
        ->where('limits', ['search_max' => 80, 'message_max' => 1000, 'contact_max' => 190]));

    // Page d'une session : ni marque « sans compte » (jamais gardée par le service worker), ni indexation.
    $response->assertHeaderMissing(TagGuestResponse::HEADER);
    expect((string) $response->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('ouvre les fiches réservées au réseau à un·e pair·e validé·e', function (): void {
    $peer = F::member('pair');
    $peer->forceFill(['status' => UserStatus::Active])->save();

    $this->actingAs($peer)->get('/annuaire')
        ->assertInertia(fn (Assert $page) => $page
            ->where('reach', 'network')
            ->has('contacts.data', 2)
            ->where('contacts.data.1.name', 'DOSSOU Inès')
            ->where('contacts.data.1.is_public', false)
            ->where('contacts.data.1.can.callback', false));
});

it('lit les filtres avec tolérance : valeur inconnue ignorée, commune sans département ignorée', function (): void {
    $this->actingAs(F::member())->get('/annuaire?role=inconnu&zone=abc&commune='.$this->commune->id.'&q=%20%20')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['q' => null, 'role' => null, 'zone' => null, 'commune' => null])
            ->has('contacts.data', 1));
});

it('refuse /annuaire (403) à un compte sans directory.view, comme l’API', function (): void {
    $this->actingAs(User::factory()->withProfile()->create())->get('/annuaire')->assertForbidden();
});

it('renvoie un·e visiteur·se vers la connexion : rien que le service worker puisse garder', function (): void {
    $this->get('/annuaire')->assertRedirect(route('connexion'));
});

it('écrit les textes de l’annuaire au tu, avec une espace insécable avant « : ; ? ! »', function (): void {
    $texts = Illuminate\Support\Arr::dot((array) trans('directory'));

    expect($texts)->not->toBeEmpty();

    foreach ($texts as $key => $text) {
        expect($text)->not->toMatch('/ [:;?!](?![a-z_])/u', (string) $key)
            ->and($text)->not->toMatch('/\b(vous|votre|vos)\b/iu', (string) $key);
    }
});
