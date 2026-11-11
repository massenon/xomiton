<?php

declare(strict_types=1);

use App\Rules\StrongPassword;
use Illuminate\Support\Facades\Validator;

/*
 * Règle serveur `password_strong` (Q30 a) : « Fort » minimum, un message par règle manquante ; le contrôle
 * « compromis » (HIBP) est coupé en environnement de test.
 */

it('accepte un mot de passe « Fort » ou « Excellent »', function (string $password): void {
    expect(Validator::make(['password' => $password], ['password' => [new StrongPassword]])->passes())->toBeTrue();
})->with(['Xomiton2026', 'Xomiton-2026!', 'Élodie2026x']);

it('explique chaque règle manquante avec un message distinct', function (string $password, array $expectedMessages): void {
    $validator = Validator::make(['password' => $password], ['password' => [new StrongPassword]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->get('password'))->toBe($expectedMessages);
})->with([
    'trop court mais complet' => ['Abc12!', ['Ton mot de passe doit contenir au moins 10 caractères.']],
    'sans majuscule' => ['xomiton2026', ['Ajoute au moins une majuscule à ton mot de passe.']],
    'sans minuscule' => ['XOMITON2026', ['Ajoute au moins une minuscule à ton mot de passe.']],
    'sans chiffre' => ['XomitonBenin', ['Ajoute au moins un chiffre à ton mot de passe.']],
    'tout manque' => ['abc', [
        'Ton mot de passe doit contenir au moins 10 caractères.',
        'Ajoute au moins une majuscule à ton mot de passe.',
        'Ajoute au moins un chiffre à ton mot de passe.',
    ]],
]);

it('ne consulte pas le service de fuites en environnement de test', function (): void {
    expect(StrongPassword::checksCompromised())->toBeFalse();
});
