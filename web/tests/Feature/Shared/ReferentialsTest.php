<?php

declare(strict_types=1);

use App\Application\Shared\Referentials;
use App\Models\Commune;
use App\Models\Need;
use App\Models\Zone;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * P3-06 : référentiels en cache 24 h (clé versionnée, invalidation par observer à l'écriture) et API publique
 * GET /api/v1/zones, violence-types, needs avec ETag dérivé du contenu, If-None-Match → 304, Cache-Control public 1 h.
 */

beforeEach(function (): void {
    $this->seed(ReferenceDataSeeder::class);
    $this->referentials = app(Referentials::class);
});

it('met les référentiels en cache et ne relit plus la base jusqu’à l’invalidation', function (): void {
    $zones = $this->referentials->zones();

    expect($zones)->toHaveCount(12)
        ->and($zones[7]['name'])->toBe('Littoral')
        ->and($zones[7]['communes'][0]['name'])->toBe('Cotonou')
        ->and(Cache::has($this->referentials->key(Referentials::ZONES)))->toBeTrue();

    DB::enableQueryLog();
    $this->referentials->zones();
    $this->referentials->violenceTypes();
    $this->referentials->zones();
    expect(DB::getQueryLog())->toHaveCount(1); // violence_types seul : premier appel
    DB::flushQueryLog();

    expect($this->referentials->needs())->toHaveCount(7)
        ->and($this->referentials->needs()[0])->toMatchArray(['slug' => 'soutien_psychologique', 'routed_role' => 'psychologue'])
        ->and($this->referentials->populations())->toHaveCount(7)
        ->and($this->referentials->categories('thematique'))->toHaveCount(6)
        ->and($this->referentials->categories('event'))->toBe([])
        ->and($this->referentials->violenceTypes()[0]['slug'])->toBe('physique');
});

it('invalide le cache à chaque écriture d’un référentiel et ignore les entrées inactives', function (): void {
    $before = $this->referentials->version();
    $this->referentials->zones();

    $zone = Zone::query()->create(['name' => 'Zone test', 'slug' => 'zone-test', 'position' => 99, 'is_active' => true]);

    expect($this->referentials->version())->toBe($before + 1)
        ->and(collect($this->referentials->zones())->pluck('name'))->toContain('Zone test');

    Commune::query()->create(['zone_id' => $zone->id, 'name' => 'Commune test', 'slug' => 'commune-test', 'position' => 1, 'is_active' => true]);
    expect(collect($this->referentials->zones())->firstWhere('slug', 'zone-test')['communes'][0]['name'])->toBe('Commune test');

    $zone->update(['is_active' => false]);
    expect(collect($this->referentials->zones())->pluck('slug'))->not->toContain('zone-test');

    // Écritures par Eloquent (les mises à jour de masse du constructeur de requêtes ne déclenchent pas l'observer).
    Need::query()->where('slug', 'autre')->firstOrFail()->delete();
    expect(collect($this->referentials->needs())->pluck('slug'))->not->toContain('autre');

    $hash = $this->referentials->hash(Referentials::NEEDS);
    Need::query()->where('slug', 'securite')->firstOrFail()->update(['label' => 'Mise en sécurité immédiate']);
    expect($this->referentials->hash(Referentials::NEEDS))->not->toBe($hash);
});

it('sert GET zones avec ETag et Cache-Control public, puis 304 sur If-None-Match tant que rien ne change', function (): void {
    $response = $this->getJson('/api/v1/zones');

    $response->assertOk()
        ->assertJsonPath('meta.count', 12)
        ->assertJsonPath('data.7.name', 'Littoral')
        ->assertJsonPath('data.7.communes.0.slug', 'cotonou')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'position', 'communes' => [['id', 'name', 'slug', 'position']]]], 'meta' => ['count']])
        ->assertHeader('ETag');

    $etag = (string) $response->headers->get('ETag');
    $cacheControl = (string) $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('max-age=3600')->toContain('public');

    $notModified = $this->withHeaders(['If-None-Match' => $etag])->getJson('/api/v1/zones');
    $notModified->assertStatus(304)->assertHeader('ETag', $etag);
    expect($notModified->getContent())->toBe('');

    Zone::query()->create(['name' => 'Zone test', 'slug' => 'zone-test', 'position' => 99, 'is_active' => true]);

    $changed = $this->withHeaders(['If-None-Match' => $etag])->getJson('/api/v1/zones');
    $changed->assertOk()->assertJsonPath('meta.count', 13);
    expect($changed->headers->get('ETag'))->not->toBe($etag);
});

it('sert les types de violence et les besoins sans authentification, au format { data, meta }', function (): void {
    $this->getJson('/api/v1/violence-types')
        ->assertOk()
        ->assertJsonPath('meta.count', 5)
        ->assertJsonPath('data.0.slug', 'physique')
        ->assertJsonStructure(['data' => [['id', 'slug', 'label', 'icon', 'description', 'position']]])
        ->assertHeader('ETag');

    $this->getJson('/api/v1/needs')
        ->assertOk()
        ->assertJsonPath('meta.count', 7)
        ->assertJsonPath('data.0.routed_role', 'psychologue')
        ->assertJsonPath('data.3.routed_role', null)
        ->assertJsonStructure(['data' => [['id', 'slug', 'label', 'routed_role', 'position']]]);

    $this->getJson('/api/v1/needs')->assertHeader('Cache-Control');
});
