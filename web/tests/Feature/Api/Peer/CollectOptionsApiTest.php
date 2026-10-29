<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Support\CaseFixtures as C;
use Tests\Support\FeedFixtures as F;

/*
 * `GET pair/collect-options` (docs/API.md §22) : listes du formulaire de collecte (natures, départements et communes,
 * populations, sexes, types de violence, tranches d'âge, orientations, étapes) pour la collecte hors ligne ; ETag / 304 ;
 * compte pair actif seulement ; référentiels actifs, aucune donnée de dossier.
 */

beforeEach(function (): void {
    C::seed();
    $this->peer = C::peer();
});

it('sert les listes du formulaire de collecte au pair', function (): void {
    $response = F::as($this->peer)->getJson('/api/v1/pair/collect-options')->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveKeys(['kinds', 'zones', 'populations', 'sexes', 'violence_types', 'age_ranges', 'referrals', 'followup_steps', 'notes_min_length', 'followup_outcomes'])
        ->and($data['populations'][0])->toHaveKeys(['id', 'label'])
        ->and($data['zones'][0])->toHaveKeys(['id', 'name', 'communes'])
        ->and($data['zones'][0]['communes'][0])->toHaveKeys(['id', 'name'])
        ->and($data['violence_types'][0])->toHaveKeys(['id', 'label'])
        ->and($data['kinds'][0])->toHaveKeys(['value', 'label'])
        ->and($data['sexes'][0])->toHaveKeys(['value', 'label'])
        ->and($data['age_ranges'][0])->toHaveKeys(['value', 'label'])
        ->and($data['notes_min_length'])->toBe(20);
});

it('ne sert que des clés de référentiel, sans fuite de dossier ni de compte', function (): void {
    $json = F::as($this->peer)->getJson('/api/v1/pair/collect-options')->assertOk()->getContent();

    expect($json)->not->toContain($this->peer->email)->not->toContain('notes"')->not->toContain('collected_by');
});

it('répond 304 quand les listes n’ont pas changé', function (): void {
    $etag = F::as($this->peer)->getJson('/api/v1/pair/collect-options')->assertOk()->headers->get('ETag');

    expect($etag)->not->toBeNull();

    F::as($this->peer)->withHeader('If-None-Match', $etag)->getJson('/api/v1/pair/collect-options')->assertStatus(304);
});

it('réserve les listes au compte pair actif : 401, 403 membre, 403 pair en attente', function (): void {
    $this->getJson('/api/v1/pair/collect-options')->assertUnauthorized();
    F::as(F::member())->getJson('/api/v1/pair/collect-options')->assertForbidden()->assertJsonPath('code', 'forbidden');

    $pending = User::factory()->pending()->withProfile()->create();
    $pending->assignRole('pair');

    F::as($pending)->getJson('/api/v1/pair/collect-options')->assertForbidden();
});
