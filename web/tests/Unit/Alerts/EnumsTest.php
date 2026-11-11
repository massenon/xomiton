<?php

declare(strict_types=1);

use App\Enums\AlertStatus;
use App\Enums\ContactChannel;
use App\Enums\RoutingFallback;

/*
 * P5-01 : enums du routage et du recontact (libellés lang/fr/enums/alerts.php, badges connus) et matrice des
 * transitions de statut d'une alerte (DATABASE_DICTIONARY §5, `clos` définitif).
 */

const KNOWN_BADGES = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'violet', 'neutral'];

it('donne un libellé et un badge connu à chaque niveau de routage, et repère les replis', function (): void {
    foreach (RoutingFallback::cases() as $case) {
        expect($case->label())->not->toBe('')->not->toStartWith('enums/')
            ->and(KNOWN_BADGES)->toContain($case->badge());
    }

    expect(array_map(static fn (RoutingFallback $f): bool => $f->isFallback(), RoutingFallback::cases()))
        ->toBe([false, true, true, true, false])
        ->and(RoutingFallback::FocalNational->level())->toBe(3)
        ->and(RoutingFallback::Manual->label())->toBe('Attribution manuelle');
});

it('donne un libellé et un badge connu à chaque canal de recontact et le déduit du moyen de contact', function (): void {
    foreach (ContactChannel::cases() as $case) {
        expect($case->label())->not->toBe('')->not->toStartWith('enums/')
            ->and(KNOWN_BADGES)->toContain($case->badge())
            ->and($case->icon())->toStartWith('bi-');
    }

    expect(ContactChannel::guessFor('nom@exemple.bj'))->toBe(ContactChannel::Email)
        ->and(ContactChannel::guessFor('+229 01 97 38 88 47'))->toBe(ContactChannel::Call)
        ->and(ContactChannel::guessFor('pas un contact'))->toBeNull()
        ->and(ContactChannel::guessFor(null))->toBeNull();
});

it('applique la matrice complète des transitions de statut', function (): void {
    $expected = [
        'nouveau' => ['pris_en_charge', 'en_cours', 'clos'],
        'pris_en_charge' => ['en_cours', 'traite', 'clos'],
        'en_cours' => ['traite', 'clos'],
        'traite' => ['clos', 'en_cours'],
        'clos' => [],
    ];

    foreach (AlertStatus::cases() as $from) {
        foreach (AlertStatus::cases() as $to) {
            expect($from->canTransitionTo($to))->toBe(in_array($to->value, $expected[$from->value], true), "{$from->value} → {$to->value}");
        }
    }
});
