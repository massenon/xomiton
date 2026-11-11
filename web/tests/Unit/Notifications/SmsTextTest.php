<?php

declare(strict_types=1);

use App\Domain\Notifications\SmsText;

/*
 * P5-03 (D6) : un SMS est compté en segments ; un seul caractère hors GSM-7 (’, –, espace insécable, « ê ») ferait
 * passer tout le message en UCS-2 (70 caractères par segment au lieu de 160).
 */

it('ramène la typographie française et les lettres hors alphabet vers GSM-7', function (): void {
    $text = SmsText::normalize("Xomiton – Fausse alerte\u{00A0}: l’alerte a été annulée. Vous êtes choisi·e… ça");

    expect($text)->toBe("Xomiton - Fausse alerte : l'alerte a été annulée. Vous etes choisi.e... ca")
        ->and(SmsText::isGsm7($text))->toBeTrue()
        ->and(SmsText::isGsm7('Xomiton – SOS'))->toBeFalse()
        ->and(SmsText::isGsm7('Réf. à Zè, Covè'))->toBeTrue();
});

it('compte les segments selon l’encodage', function (): void {
    expect(SmsText::segments(str_repeat('a', 160)))->toBe(1)
        ->and(SmsText::segments(str_repeat('a', 161)))->toBe(2)
        ->and(SmsText::segments(str_repeat('a', 306)))->toBe(2)
        ->and(SmsText::segments(str_repeat('a', 70).'–'))->toBe(2)
        ->and(SmsText::length('a€'))->toBe(3)
        ->and(SmsText::capacity('abc', 2))->toBe(306)
        ->and(SmsText::capacity('abc 🙂', 1))->toBe(70);
});

it('tronque en unités, points de suspension GSM-7 compris', function (): void {
    $cut = SmsText::limit(str_repeat('mot ', 60), 160);

    expect(SmsText::length($cut))->toBeLessThanOrEqual(160)
        ->and($cut)->toEndWith('...')
        ->and(SmsText::limit('court', 160))->toBe('court');
});
