<?php

declare(strict_types=1);

use App\Domain\Auth\PasswordStrength;

/*
 * Jauge de solidité (LOGIN §4, Q30 a) : mêmes règles que resources/js/public/password-meter.js.
 */

it('classe les mots de passe du plus court au plus solide', function (string $password, PasswordStrength $expected): void {
    expect(PasswordStrength::score($password))->toBe($expected);
})->with([
    'vide' => ['', PasswordStrength::TooShort],
    'neuf caractères complets' => ['Abcdef12!', PasswordStrength::TooShort],
    'dix minuscules' => ['abcdefghij', PasswordStrength::Weak],
    'dix chiffres' => ['0123456789', PasswordStrength::Weak],
    'minuscules + chiffres' => ['abcdefgh12', PasswordStrength::Medium],
    'majuscules + minuscules' => ['Abcdefghij', PasswordStrength::Medium],
    'majuscule + minuscule + chiffre' => ['Abcdefgh12', PasswordStrength::Strong],
    'accents comptés comme lettres' => ['Élodie2026x', PasswordStrength::Strong],
    'avec symbole' => ['Abcdefgh12!', PasswordStrength::Excellent],
    'avec espace comme seul caractère spécial' => ['Abcdefgh 12', PasswordStrength::Strong],
]);

it('expose les règles cochées en direct dans l’ordre d’affichage', function (): void {
    $checks = PasswordStrength::checks('Xomiton-2026');

    expect(array_keys($checks))->toBe(['length', 'uppercase', 'lowercase', 'digit', 'symbol'])
        ->and($checks)->toBe(['length' => true, 'uppercase' => true, 'lowercase' => true, 'digit' => true, 'symbol' => true])
        ->and(PasswordStrength::checks('court'))->toBe(['length' => false, 'uppercase' => false, 'lowercase' => true, 'digit' => false, 'symbol' => false]);
});

it('fournit segments, ton et libellé traduit, « Fort » étant le plancher accepté', function (): void {
    expect(PasswordStrength::TooShort->segments())->toBe(0)
        ->and(PasswordStrength::Excellent->segments())->toBe(PasswordStrength::SEGMENTS)
        ->and(PasswordStrength::Weak->tone())->toBe('danger')
        ->and(PasswordStrength::Medium->tone())->toBe('warning')
        ->and(PasswordStrength::Strong->tone())->toBe('success')
        ->and(PasswordStrength::Medium->isAcceptable())->toBeFalse()
        ->and(PasswordStrength::Strong->isAcceptable())->toBeTrue()
        ->and(PasswordStrength::TooShort->label())->toBe('Trop court')
        ->and(PasswordStrength::Weak->label())->toBe('Faible')
        ->and(PasswordStrength::Medium->label())->toBe('Moyen')
        ->and(PasswordStrength::Strong->label())->toBe('Fort')
        ->and(PasswordStrength::Excellent->label())->toBe('Excellent');
});
