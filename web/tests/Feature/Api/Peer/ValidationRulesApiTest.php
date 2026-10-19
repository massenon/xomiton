<?php

declare(strict_types=1);

use Tests\Support\CaseFixtures as C;
use Tests\Support\FeedFixtures as F;

/*
 * `GET pair/validation-rules` (P9-02, docs/API.md §22) : règles actives du catalogue pour la collecte hors ligne
 * (cache mobile), sans les clés documentaires de l'administration, listes fermées de la collecte, ETag / 304.
 */

beforeEach(function (): void {
    C::seed();
    $this->peer = C::peer();
});

it('sert les règles actives et leurs paramètres utiles au client', function (): void {
    C::disable('password_strong');

    $response = F::as($this->peer)->getJson('/api/v1/pair/validation-rules')->assertOk();
    $keys = array_column($response->json('data'), 'key');

    expect($keys)->toContain('required', 'date_not_future', 'age_range', 'plausibility', 'duplicates')
        ->not->toContain('password_strong')
        ->and($response->json('data.0.key'))->toBe('required')
        ->and($response->json('data.0.locked'))->toBeTrue();

    $plausibility = collect($response->json('data'))->firstWhere('key', 'plausibility');
    $duplicates = collect($response->json('data'))->firstWhere('key', 'duplicates');

    expect($plausibility['params'])->toMatchArray(['age_min' => 10, 'age_max' => 99, 'max_past_years' => 5])
        ->and($plausibility['params'])->not->toHaveKey('forms')
        ->and($duplicates['params'])->toBe(['fields' => ['date', 'commune', 'population', 'age']])
        ->and($response->json('meta.updated_at'))->not->toBeNull()
        ->and($response->json('meta.options.referrals'))->toContain('Gynécologue')
        ->and($response->json('meta.options.notes_min_length'))->toBe(20)
        ->and(array_column($response->json('meta.options.age_ranges'), 'value'))->toBe(['10-14', '15-17', '18-24', '25-34', '35-49', '50+']);
});

it('réduit les tranches d’âge proposées aux bornes réglées', function (): void {
    C::params('plausibility', ['age_min' => 15]);

    $response = F::as($this->peer)->getJson('/api/v1/pair/validation-rules')->assertOk();

    expect(array_column($response->json('meta.options.age_ranges'), 'value'))->not->toContain('10-14');
});

it('propose toutes les tranches d’âge quand age_range est désactivée, quelles que soient les bornes', function (): void {
    C::params('plausibility', ['age_min' => 15]);
    C::disable('age_range');

    $response = F::as($this->peer)->getJson('/api/v1/pair/validation-rules')->assertOk();

    expect(array_column($response->json('meta.options.age_ranges'), 'value'))->toBe(['10-14', '15-17', '18-24', '25-34', '35-49', '50+'])
        ->and(array_column($response->json('data'), 'key'))->not->toContain('age_range');
});

it('répond 304 quand le catalogue n’a pas changé', function (): void {
    $etag = F::as($this->peer)->getJson('/api/v1/pair/validation-rules')->assertOk()->headers->get('ETag');

    expect($etag)->not->toBeNull();

    F::as($this->peer)->withHeader('If-None-Match', $etag)->getJson('/api/v1/pair/validation-rules')->assertStatus(304);
});

it('refuse le catalogue à un·e membre', function (): void {
    F::as(F::member())->getJson('/api/v1/pair/validation-rules')->assertForbidden();
});
