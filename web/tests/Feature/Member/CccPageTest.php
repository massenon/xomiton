<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Http\Middleware\TagGuestResponse;
use App\Models\CccTool;
use App\Models\Law;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures as F;

/*
 * Écran Vue « Outils CCC » `/ccc` (P8-12 ; P9-05 : une seule implémentation pour le membre et le pair, drapeau
 * `presentation`) : page Inertia `Member/Ccc`, outils publiés seulement sous la forme de l'API (docs/API.md §19 bis),
 * permission `ccc_tools.view`, noindex, jamais gardée hors ligne. Ce n'est plus un écran provisoire.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    CccTool::factory()->create(['key' => 'm1', 'title' => 'Module A', 'position' => 1, 'chapters' => ['Un', 'Deux']]);
    CccTool::factory()->unpublished()->create(['key' => 'm2', 'title' => 'Module masqué', 'position' => 2]);
    CccTool::factory()->imageBox()->create(['key' => 'bi1', 'title' => 'Boîte A']);
    CccTool::factory()->imageBox()->unpublished()->create(['key' => 'bi2', 'title' => 'Boîte masquée']);
    CccTool::factory()->comic()->create(['key' => 'bd1', 'title' => 'BD A']);
    CccTool::factory()->comic()->unpublished()->create(['key' => 'bd2', 'title' => 'BD masquée']);
    Law::factory()->create();
    Law::factory()->unpublished()->create();
});

it('sert /ccc à un·e membre : outils publiés par famille, lois, sans mode présentation', function (): void {
    $response = $this->actingAs(F::member())->get('/ccc')->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Member/Ccc')
        ->where('title', 'Outils CCC')
        ->where('presentation', false)
        ->has('tools.manuel', 1)
        ->where('tools.manuel.0.title', 'Module A')
        ->where('tools.manuel.0.chapters', ['Un', 'Deux'])
        ->has('tools.boites_images', 1)
        ->where('tools.boites_images.0.title', 'Boîte A')
        ->has('tools.boites_images.0.pages', 1)
        ->has('tools.bd', 1)
        ->where('tools.bd.0.title', 'BD A')
        ->where('laws.count', 1)
        ->where('laws.url', route('lois')));

    // Jamais un outil masqué, nulle part dans la page.
    foreach (['Module masqué', 'Boîte masquée', 'BD masquée'] as $hidden) {
        expect($response->getContent())->not->toContain($hidden);
    }

    $response->assertHeaderMissing(TagGuestResponse::HEADER);
    expect((string) $response->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('active le mode présentation pour un·e pair·e validé·e seulement', function (): void {
    $peer = F::member('pair');
    $peer->forceFill(['status' => UserStatus::Active])->save();

    $this->actingAs($peer)->get('/ccc')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Member/Ccc')->where('presentation', true));

    // RG-03 : un compte pair en attente de validation reste membre.
    $pending = F::member('pair');
    $pending->forceFill(['status' => UserStatus::Pending])->save();
    $this->actingAs($pending)->get('/ccc')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('presentation', false));
});

it('redirige vers la connexion sans compte et refuse (403) sans permission de lecture', function (): void {
    $this->get('/ccc')->assertRedirect(route('connexion'));

    $member = F::member();
    $member->syncRoles([]);
    $member->syncPermissions([]);
    $this->actingAs($member->fresh())->get('/ccc')->assertForbidden();
});

it('n’est plus un écran provisoire : page Vue dédiée, jamais Member/Placeholder', function (): void {
    $this->actingAs(F::member())->get('/ccc')
        ->assertInertia(fn (Assert $page) => $page->component('Member/Ccc')->missing('prototype'));

    expect(app('router')->getRoutes()->getByName('ccc')?->getActionName())->toContain('CccPageController')
        ->and(app('router')->getRoutes()->getByName('ccc')?->defaults)->not->toHaveKey('prototype');
});

it('relie le tableau de bord pair et ses tâches à l’écran réel /ccc', function (): void {
    $peer = F::member('pair');
    $peer->forceFill(['status' => UserStatus::Active])->save();

    $this->actingAs($peer)->get('/espace-pair')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PeerSpace/Home'));

    // Raccourci « Outils CCC » et tâche « Préparer la prochaine séance » : même adresse, écran réel.
    expect(route('ccc', absolute: false))->toBe('/ccc');
    $this->actingAs($peer)->get(route('ccc', absolute: false))
        ->assertInertia(fn (Assert $page) => $page->component('Member/Ccc'));
});
