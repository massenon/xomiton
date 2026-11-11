<?php

declare(strict_types=1);

use App\Domain\Alerts\TrackingCode;
use App\Models\Alert;

/*
 * P5-01 : code de suivi — 10 caractères d'un alphabet sans ambiguïté, saisie tolérante, affichage « SIG-… ».
 */

it('génère 10 caractères de l’alphabet sans 0, O, 1 ni I', function (): void {
    foreach (range(1, 50) as $ignored) {
        $code = TrackingCode::random();

        expect($code)->toHaveLength(10)->toMatch('/^[A-HJ-NP-Z2-9]{10}$/')
            ->and(TrackingCode::isValid($code))->toBeTrue();
    }

    expect(Alert::TRACKING_ALPHABET)->toBe(TrackingCode::ALPHABET)
        ->and(TrackingCode::ALPHABET)->not->toContain('0')->not->toContain('O')->not->toContain('1')->not->toContain('I');
});

it('est reproductible avec un générateur injecté', function (): void {
    $sequence = static function (): Closure {
        $i = 0;

        return static function (int $min, int $max) use (&$i): int {
            return ($i++ * 7) % ($max + 1);
        };
    };

    expect(TrackingCode::random(10, $sequence()))->toBe(TrackingCode::random(10, $sequence()))
        ->and(TrackingCode::random(4, static fn (int $min, int $max): int => $min))->toBe('AAAA');

    expect(fn () => TrackingCode::random(0))->toThrow(InvalidArgumentException::class);
});

it('normalise la saisie et refuse un code invalide sans exception', function (): void {
    expect(TrackingCode::normalize('sig-a7k2 m9p4-qx'))->toBe('A7K2M9P4QX')
        ->and(TrackingCode::normalize(' A7K2M9P4QX '))->toBe('A7K2M9P4QX')
        ->and(TrackingCode::normalize('SIGA7K2M9P4QX'))->toBe('A7K2M9P4QX')
        ->and(TrackingCode::normalize('SIG2345ABCDE'))->toBeNull()
        ->and(TrackingCode::normalize('A7K2M9P4Q0'))->toBeNull()
        ->and(TrackingCode::normalize('A7K2M9P4Q'))->toBeNull()
        ->and(TrackingCode::normalize(''))->toBeNull()
        ->and(TrackingCode::display('A7K2M9P4QX'))->toBe('SIG-A7K2M9P4QX');
});

it('ne confond jamais le préfixe avec le code : la lettre I est hors alphabet', function (): void {
    expect(TrackingCode::ALPHABET)->not->toContain('I')
        ->and(TrackingCode::normalize('sig-SIGABCDE23'))->toBeNull();
});
