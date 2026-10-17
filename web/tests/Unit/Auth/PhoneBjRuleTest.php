<?php

declare(strict_types=1);

use App\Rules\PhoneBj;
use Illuminate\Support\Facades\Validator;

/*
 * Règle `phone_bj` du catalogue : plan 2024 à 10 chiffres (01…), ancien plan à 8 chiffres, indicatif facultatif.
 */

it('normalise les écritures béninoises vers « +229 01 XX XX XX XX »', function (string $input, string $expected): void {
    expect(PhoneBj::normalize($input))->toBe($expected);
})->with([
    'déjà canonique' => ['+229 01 97 38 88 47', '+229 01 97 38 88 47'],
    'sans espaces ni indicatif' => ['0197388847', '+229 01 97 38 88 47'],
    'ancien plan 8 chiffres' => ['97 38 88 47', '+229 01 97 38 88 47'],
    'indicatif 00229 et points' => ['00229 97.38.88.47', '+229 01 97 38 88 47'],
    'indicatif collé 10 chiffres' => ['+2290197388847', '+229 01 97 38 88 47'],
    'tirets et parenthèses' => ['(+229) 01-97-38-88-47', '+229 01 97 38 88 47'],
]);

it('rejette les numéros qui ne sont pas béninois', function (string $input): void {
    expect(PhoneBj::normalize($input))->toBeNull()
        ->and(PhoneBj::isValid($input))->toBeFalse();
})->with([
    'lettres' => ['12-AB'],
    'trop court' => ['97 38 88'],
    'dix chiffres hors plan 01' => ['02 97 38 88 47'],
    'autre pays' => ['+33 6 12 34 56 78'],
    'vide' => [''],
]);

it('produit le message français du catalogue', function (): void {
    $validator = Validator::make(['phone' => '12-AB'], ['phone' => [new PhoneBj]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('phone'))->toBe('Saisis un numéro béninois valide, par exemple +229 01 97 38 88 47.');

    expect(Validator::make(['phone' => '01 55 12 34 56'], ['phone' => [new PhoneBj]])->passes())->toBeTrue();
});
