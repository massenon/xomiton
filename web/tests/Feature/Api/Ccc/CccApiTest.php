<?php

declare(strict_types=1);

use App\Models\CccTool;
use App\Models\Law;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Support\FeedFixtures as F;

/*
 * Outils CCC en lecture (P8-12, docs/API.md §19 bis) : `GET ccc` — outils publiés seulement par famille (manuel, boîtes à
 * images, BD), ordre stocké (position puis clé, chapitres et planches dans l'ordre du JSON), image de remplacement, `alt` par
 * planche, textes de loi publiés en lien vers /lois ; `GET ccc/{key}` — 404 neutre pour un outil masqué, inconnu ou de
 * la famille des lois ; 401 sans compte ; `no-store`.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('exige un compte : 401 sans connexion', function (): void {
    $this->getJson('/api/v1/ccc')->assertUnauthorized();
    $this->getJson('/api/v1/ccc/m1')->assertUnauthorized();
});

it('sert à un·e membre les outils publiés par famille, dans l’ordre, sans les outils masqués', function (): void {
    CccTool::factory()->create(['key' => 'm2', 'title' => 'Module B', 'position' => 2, 'chapters' => ['Un', 'Deux'], 'duration_minutes' => 60]);
    CccTool::factory()->create(['key' => 'm1', 'title' => 'Module A', 'position' => 1, 'chapters' => ['Premier', 'Second', 'Troisième'], 'duration_minutes' => 45]);
    CccTool::factory()->unpublished()->create(['key' => 'm3', 'title' => 'Module masqué', 'position' => 0]);
    CccTool::factory()->imageBox()->create(['key' => 'bi1', 'title' => 'Le consentement', 'description' => 'Consigne de séance', 'position' => 1]);
    CccTool::factory()->imageBox()->unpublished()->create(['key' => 'bi2', 'title' => 'Boîte masquée']);
    CccTool::factory()->comic()->create(['key' => 'bd1', 'title' => 'Léa et le « non »', 'position' => 1]);
    Law::factory()->create();
    Law::factory()->create();
    Law::factory()->unpublished()->create();

    $response = F::as(F::member())->getJson('/api/v1/ccc')->assertOk();

    expect(array_keys($response->json('data')))->toBe(['manuel', 'boites_images', 'bd'])
        ->and(array_column($response->json('data.manuel'), 'key'))->toBe(['m1', 'm2'])
        ->and(array_column($response->json('data.boites_images'), 'key'))->toBe(['bi1'])
        ->and(array_column($response->json('data.bd'), 'key'))->toBe(['bd1'])
        ->and($response->json('meta.laws'))->toBe(['count' => 2, 'url' => route('lois')])
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $module = $response->json('data.manuel.0');
    expect($module)->toMatchArray([
        'key' => 'm1', 'family' => 'manuel', 'title' => 'Module A', 'duration_minutes' => 45,
        'chapters' => ['Premier', 'Second', 'Troisième'], 'chapters_count' => 3, 'cover' => null,
    ])->and($module)->not->toHaveKeys(['pages', 'is_published', 'position', 'id']);

    $box = $response->json('data.boites_images.0');
    expect($box)->toMatchArray(['key' => 'bi1', 'family' => 'boites_images', 'description' => 'Consigne de séance', 'pages_count' => 1])
        ->and($box['pages'][0])->toMatchArray(['position' => 1])
        ->and($box['pages'][0]['image'])->toEndWith('assets/images/xomiton/hero-09.jpg')
        ->and($box['cover'])->toEndWith('assets/images/xomiton/hero-09.jpg')
        ->and($box)->not->toHaveKeys(['chapters', 'duration_minutes']);
});

it('garde les planches et les chapitres dans l’ordre stocké, avec un alt par planche', function (): void {
    CccTool::factory()->comic()->create(['key' => 'bd1', 'title' => 'Ma BD', 'pages' => [
        ['image' => 'assets/images/xomiton/hero-10.jpg', 'caption' => 'Troisième dans l’ordre alphabétique ? Non, première.', 'alt' => 'Planche d’ouverture'],
        ['image' => 'assets/images/xomiton/hero-09.jpg', 'caption' => '', 'alt' => ''],
        ['image' => 'assets/images/xomiton/hero-12.jpg', 'caption' => 'Fin', 'alt' => 'Planche finale'],
    ]]);
    CccTool::factory()->create(['key' => 'm1', 'chapters' => ['Zèbre', '  ', 'Abeille', 12, 'Mulet']]);

    $comic = F::as(F::member())->getJson('/api/v1/ccc/bd1')->assertOk()->json('data');

    expect(array_column($comic['pages'], 'position'))->toBe([1, 2, 3])
        ->and(array_column($comic['pages'], 'alt'))->toBe(['Planche d’ouverture', 'Ma BD – 2', 'Planche finale'])
        ->and($comic['pages'][1]['caption'])->toBeNull()
        ->and($comic['pages'][2]['caption'])->toBe('Fin')
        ->and($comic['pages_count'])->toBe(3);

    // Chapitres : textes non vides seulement, dans l'ordre stocké (jamais triés).
    expect(F::as(F::member())->getJson('/api/v1/ccc/m1')->json('data.chapters'))->toBe(['Zèbre', 'Abeille', 'Mulet']);
});

it('remplace une image manquante par l’image de remplacement et garde une adresse absolue', function (): void {
    CccTool::factory()->imageBox()->create(['key' => 'bi1', 'image_path' => 'ccc/introuvable.jpg', 'pages' => [
        ['image' => 'ccc/introuvable.jpg', 'caption' => 'a', 'alt' => 'A'],
        ['image' => 'https://exemple.test/planche.jpg', 'caption' => 'b', 'alt' => 'B'],
    ]]);

    $box = F::as(F::member())->getJson('/api/v1/ccc/bi1')->assertOk()->json('data');

    expect($box['cover'])->toEndWith('assets/images/xomiton/covers/og-default.jpg')
        ->and($box['pages'][0]['image'])->toEndWith('assets/images/xomiton/covers/og-default.jpg')
        ->and($box['pages'][1]['image'])->toBe('https://exemple.test/planche.jpg');
});

it('renvoie des familles vides quand rien n’est publié', function (): void {
    CccTool::factory()->unpublished()->create(['key' => 'm1']);

    $response = F::as(F::member())->getJson('/api/v1/ccc')->assertOk();

    expect($response->json('data'))->toBe(['manuel' => [], 'boites_images' => [], 'bd' => []])
        ->and($response->json('meta.laws.count'))->toBe(0);
});

it('sert un outil publié et répond au même 404 pour un outil masqué, inconnu ou une fiche de loi', function (): void {
    CccTool::factory()->create(['key' => 'm1', 'title' => 'Module A']);
    CccTool::factory()->unpublished()->create(['key' => 'm2']);
    CccTool::factory()->create(['key' => 'loi1', 'kind' => App\Enums\CccKind::Law]);

    $ok = F::as(F::member())->getJson('/api/v1/ccc/m1')->assertOk();
    expect($ok->json('data.title'))->toBe('Module A')->and($ok->headers->get('Cache-Control'))->toContain('no-store');

    $hidden = F::as(F::member())->getJson('/api/v1/ccc/m2')->assertNotFound();
    $unknown = F::as(F::member())->getJson('/api/v1/ccc/m99')->assertNotFound();
    $law = F::as(F::member())->getJson('/api/v1/ccc/loi1')->assertNotFound();
    expect($hidden->json())->toBe($unknown->json())->and($law->json())->toBe($unknown->json())
        ->and($hidden->json('code'))->toBe('not_found');
    F::as(F::member())->getJson('/api/v1/ccc/pas-une-cle')->assertNotFound();
});

it('sert aussi un·e pair·e, et refuse un compte sans permission de lecture', function (): void {
    CccTool::factory()->create(['key' => 'm1']);
    $peer = F::member('pair');

    F::as($peer)->getJson('/api/v1/ccc')->assertOk()->assertJsonCount(1, 'data.manuel');

    $member = F::member();
    $member->syncRoles([]);
    $member->syncPermissions([]);
    F::as($member->fresh())->getJson('/api/v1/ccc')->assertForbidden();
    F::as($member->fresh())->getJson('/api/v1/ccc/m1')->assertForbidden();
});
