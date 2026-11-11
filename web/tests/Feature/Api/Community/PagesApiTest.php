<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PageFollower;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Support\FeedFixtures as F;

/*
 * P8-03, docs/API.md §14 : créer une page (create-page.php), **en attente** jusqu'à l'approbation de la modération
 * (P12-05 : page non approuvée invisible des autres membres), liste des pages approuvées (catégories, recherche,
 * onglets), suivre / ne plus suivre (idempotent).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = F::member();
    $this->bea = F::member();
});

it('crée une page en attente : visible de sa propriétaire et de la modération seulement', function (): void {
    $response = F::as($this->awa)->postJson('/api/v1/pages', [
        'name' => 'Clinique amie de Parakou', 'category' => 'Santé', 'description' => 'Consultations confidentielles.', 'website' => 'https://exemple.bj',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.status_label', 'En attente de vérification')
        ->assertJsonPath('data.is_mine', true)
        ->assertJsonPath('data.following', true)
        ->assertJsonPath('data.followers_count', 1)
        ->assertJsonPath('data.can.follow', false);

    $ulid = (string) $response->json('data.id');
    expect($response->headers->get('Location'))->toEndWith('/pages/'.$ulid)
        ->and(Page::query()->where('ulid', $ulid)->value('is_approved'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'page.created')->exists())->toBeTrue();

    F::as($this->bea)->getJson('/api/v1/pages/'.$ulid)->assertNotFound();
    F::as($this->bea)->postJson('/api/v1/pages/'.$ulid.'/follow')->assertNotFound();
    F::as($this->bea)->getJson('/api/v1/pages')->assertOk()->assertJsonCount(0, 'data');
    F::as($this->awa)->getJson('/api/v1/pages')->assertJsonCount(0, 'data');
    F::as($this->awa)->getJson('/api/v1/pages?tab=mine')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending');
    F::as($this->awa)->getJson('/api/v1/pages/'.$ulid)->assertOk();
    F::as(F::member('moderateur'))->getJson('/api/v1/pages/'.$ulid)->assertOk()->assertJsonPath('data.is_mine', false);

    Page::query()->where('ulid', $ulid)->update(['is_approved' => true, 'approved_at' => now()]);
    F::as($this->bea)->getJson('/api/v1/pages/'.$ulid)->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.can.follow', true);
    F::as($this->bea)->getJson('/api/v1/pages')->assertJsonCount(1, 'data');
});

it('valide le nom, la catégorie et le site ; e-mail confirmé exigé', function (): void {
    F::as($this->awa)->postJson('/api/v1/pages', ['name' => 'ab', 'category' => 'Santé'])
        ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Le nom de la page doit contenir au moins 3 caractères.');
    F::as($this->awa)->postJson('/api/v1/pages', ['name' => 'Clinique', 'category' => 'Cuisine'])
        ->assertUnprocessable()->assertJsonPath('errors.category.0', 'Choisis une catégorie de la liste.');
    F::as($this->awa)->postJson('/api/v1/pages', ['name' => 'Clinique', 'category' => 'Santé', 'website' => 'javascript:alert(1)'])
        ->assertUnprocessable()->assertJsonValidationErrors('website');
    F::as(F::member(verified: false))->postJson('/api/v1/pages', ['name' => 'Clinique', 'category' => 'Santé'])->assertForbidden();
    expect(Page::query()->count())->toBe(0);
});

it('suit et ne suit plus une page approuvée, sans doublon ni compteur négatif', function (): void {
    $page = Page::factory()->create(['followers_count' => 0]);
    $url = '/api/v1/pages/'.$page->ulid.'/follow';

    F::as($this->bea)->postJson($url)->assertOk()->assertJsonPath('data', ['following' => true, 'followers_count' => 1, 'changed' => true]);
    F::as($this->bea)->postJson($url)->assertOk()->assertJsonPath('data.changed', false)->assertJsonPath('data.followers_count', 1);
    F::as($this->bea)->getJson('/api/v1/pages?tab=following')->assertJsonCount(1, 'data')->assertJsonPath('data.0.following', true);
    F::as($this->bea)->deleteJson($url)->assertOk()->assertJsonPath('data', ['following' => false, 'followers_count' => 0, 'changed' => true]);
    F::as($this->bea)->deleteJson($url)->assertOk()->assertJsonPath('data.changed', false)->assertJsonPath('data.followers_count', 0);
    expect(PageFollower::query()->count())->toBe(0);

    $inactive = Page::factory()->inactive()->create();
    F::as($this->bea)->postJson('/api/v1/pages/'.$inactive->ulid.'/follow')->assertNotFound();
});

it('filtre par catégorie et recherche ; catégories des pages visibles dans meta', function (): void {
    Page::factory()->create(['name' => 'Clinique amie', 'category' => 'Santé', 'is_featured' => true]);
    Page::factory()->create(['name' => 'Juristes solidaires', 'category' => 'Juridique', 'description' => 'Aide juridique gratuite']);
    Page::factory()->pending()->create(['name' => 'Réseau en attente', 'category' => 'Réseau']);

    F::as($this->bea)->getJson('/api/v1/pages')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Clinique amie')
        ->assertJsonPath('data.0.is_featured', true)
        ->assertJsonPath('meta.categories', ['Santé', 'Juridique']);
    F::as($this->bea)->getJson('/api/v1/pages?category=Juridique')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Juristes solidaires');
    F::as($this->bea)->getJson('/api/v1/pages?q=gratuite')->assertJsonCount(1, 'data');
    F::as($this->bea)->getJson('/api/v1/pages?q=attente')->assertJsonCount(0, 'data');
    F::as($this->bea)->getJson('/api/v1/pages?tab=partout')->assertUnprocessable();
});

it('refuse à la propriétaire de suivre sa page en attente (403) ; elle la suit déjà depuis sa création', function (): void {
    $ulid = (string) F::as($this->awa)->postJson('/api/v1/pages', ['name' => 'Clinique amie', 'category' => 'Santé'])->assertCreated()->json('data.id');

    F::as($this->awa)->postJson('/api/v1/pages/'.$ulid.'/follow')->assertForbidden();
    expect(Page::query()->where('ulid', $ulid)->value('followers_count'))->toBe(1);
});
