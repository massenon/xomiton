<?php

declare(strict_types=1);

use App\Models\Commune;
use App\Models\User;
use App\Models\Zone;
use App\Rules\CommuneInZone;
use App\Rules\Pseudo;
use Illuminate\Support\Facades\Validator;

/*
 * Règles qui lisent la base : pseudonyme unique sans tenir compte de la casse (comptes supprimés inclus) et commune
 * cohérente avec le département (liste en cascade).
 */

it('valide un pseudonyme : longueur, caractères autorisés, unicité insensible à la casse', function (): void {
    $existing = User::factory()->create(['pseudo' => 'Awa_S']);
    User::factory()->create(['pseudo' => 'supprimee'])->delete();

    $check = fn (string $pseudo, ?int $ignore = null) => Validator::make(['pseudo' => $pseudo], ['pseudo' => [new Pseudo($ignore)]]);

    expect($check('Chantal.H-2026')->passes())->toBeTrue()
        ->and($check('Éléonore')->passes())->toBeTrue()
        ->and($check('ab')->errors()->first('pseudo'))->toBe('Ton pseudonyme doit contenir entre 3 et 40 caractères.')
        ->and($check(str_repeat('a', 41))->fails())->toBeTrue()
        ->and($check('awa s')->errors()->first('pseudo'))->toBe('Ton pseudonyme ne peut contenir que des lettres, des chiffres, des points, des tirets et des tirets bas.')
        ->and($check('awa@s')->fails())->toBeTrue()
        ->and($check('AWA_s')->errors()->first('pseudo'))->toBe('Ce pseudonyme est déjà pris. Choisis-en un autre.')
        ->and($check('Supprimee')->fails())->toBeTrue()
        ->and($check('AWA_s', $existing->id)->passes())->toBeTrue();
});

it('exige une commune active du département choisi', function (): void {
    $zone = Zone::factory()->create();
    $other = Zone::factory()->create();
    $commune = Commune::factory()->create(['zone_id' => $zone->id, 'is_active' => true]);
    $inactive = Commune::factory()->create(['zone_id' => $zone->id, 'is_active' => false]);

    $check = fn (array $data) => Validator::make($data, ['commune_id' => ['nullable', new CommuneInZone('zone_id')]]);

    expect($check(['zone_id' => $zone->id, 'commune_id' => $commune->id])->passes())->toBeTrue()
        ->and($check(['zone_id' => $other->id, 'commune_id' => $commune->id])->errors()->first('commune_id'))->toBe('Cette commune n’appartient pas au département choisi.')
        ->and($check(['zone_id' => $zone->id, 'commune_id' => $inactive->id])->fails())->toBeTrue()
        ->and($check(['commune_id' => $commune->id])->fails())->toBeTrue()
        ->and($check(['zone_id' => $zone->id, 'commune_id' => null])->passes())->toBeTrue();
});
