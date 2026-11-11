<?php

declare(strict_types=1);

use App\Domain\Messaging\ConversationRules;
use Carbon\CarbonImmutable;

/*
 * P8-01 : règles de la messagerie sans base de données (écrire, aperçu discret, corps, sourdine, curseur de lecture).
 */

it('laisse écrire une personne présente ; en privé seulement sans blocage et si l’autre est joignable', function (): void {
    expect(ConversationRules::canSend(true, isGroup: false))->toBeTrue()
        ->and(ConversationRules::canSend(false, isGroup: true))->toBeFalse()
        ->and(ConversationRules::canSend(true, isGroup: false, blocked: true))->toBeFalse()
        ->and(ConversationRules::canSend(true, isGroup: false, otherReachable: false))->toBeFalse()
        ->and(ConversationRules::canSend(true, isGroup: true, otherReachable: false, blocked: true))->toBeTrue();
});

it('masque l’aperçu en mode discret ou aperçus masqués', function (): void {
    expect(ConversationRules::previewVisible(false, false))->toBeTrue()
        ->and(ConversationRules::previewVisible(true, false))->toBeFalse()
        ->and(ConversationRules::previewVisible(false, true))->toBeFalse();
});

it('normalise le corps : bords, fins de ligne, lignes vides répétées', function (): void {
    expect(ConversationRules::normalizeBody("  Bonjour\r\n\r\n\r\n\r\nÀ bientôt  "))->toBe("Bonjour\n\nÀ bientôt")
        ->and(ConversationRules::normalizeBody(null))->toBe('');
});

it('calcule la fin de sourdine (null = jusqu’à réactivation)', function (): void {
    $now = CarbonImmutable::parse('2026-10-18 10:00:00');

    expect(ConversationRules::mutedUntil(480, $now)->toDateTimeString())->toBe('2026-10-18 18:00:00')
        ->and(ConversationRules::mutedUntil(null, $now)->year)->toBe(2126);
});

it('ne recule jamais le curseur de lecture ni ne le pousse au-delà du dernier message', function (): void {
    expect(ConversationRules::readCursor(null, null, 12))->toBe(12)
        ->and(ConversationRules::readCursor(10, 5, 12))->toBe(10)
        ->and(ConversationRules::readCursor(10, 99, 12))->toBe(12)
        ->and(ConversationRules::readCursor(null, null, null))->toBeNull()
        ->and(ConversationRules::readCursor(4, null, null))->toBe(4);
});
