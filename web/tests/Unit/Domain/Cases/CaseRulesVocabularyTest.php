<?php

declare(strict_types=1);

use App\Domain\Cases\CaseNotes;
use App\Domain\Cases\CaseReferral;
use App\Domain\Cases\DuplicateCriteria;
use App\Domain\Cases\FollowupStep;
use App\Domain\Cases\ValidationRuleKey;

/*
 * Règles pures de la collecte (P9-01) : notes vides ou ≥ 20 caractères (`min_len_20`), champs de doublons, listes
 * fermées (orientation, étapes de suivi), clés du catalogue.
 */

it('accepte des notes vides ou d’au moins 20 caractères, espaces ignorés', function (?string $notes, bool $ok): void {
    expect(CaseNotes::isAcceptable($notes))->toBe($ok);
})->with([
    [null, true],
    ['', true],
    ['    ', true],
    ['Menaces', false],
    ['   Menaces répétées      ', false],
    ['Menaces répétées par un voisin.', true],
]);

it('rogne les notes et les vide à null', function (): void {
    expect(CaseNotes::normalize('  Suivi  '))->toBe('Suivi')
        ->and(CaseNotes::normalize('   '))->toBeNull()
        ->and(CaseNotes::normalize(null))->toBeNull();
});

it('compare les champs de doublons du catalogue, ou ceux par défaut', function (): void {
    expect(DuplicateCriteria::fromParams(null)->columns())->toBe(['occurred_on', 'commune_id', 'population_id', 'age_range'])
        ->and(DuplicateCriteria::fromParams(['fields' => ['type', 'sexe', 'inconnu', 'type']])->columns())->toBe(['violence_type_id', 'sex'])
        ->and(DuplicateCriteria::fromParams(['fields' => ['inconnu']])->fields)->toBe(DuplicateCriteria::DEFAULT_FIELDS);
});

it('ne voit aucun doublon si un champ comparé est vide', function (): void {
    $criteria = DuplicateCriteria::fromParams(['fields' => ['date', 'commune']]);

    expect($criteria->valuesFrom(['occurred_on' => '2026-03-02', 'commune_id' => 4]))->toBe(['occurred_on' => '2026-03-02', 'commune_id' => 4])
        ->and($criteria->valuesFrom(['occurred_on' => '2026-03-02', 'commune_id' => null]))->toBeNull();
});

it('ferme les listes d’orientation et d’étapes de suivi sur celles du prototype', function (): void {
    expect(CaseReferral::isValid('Gynécologue'))->toBeTrue()
        ->and(CaseReferral::isValid('Lesbienne'))->toBeFalse()
        ->and(FollowupStep::isValid('Suivi à 30 jours'))->toBeTrue()
        ->and(FollowupStep::isValid('Autre'))->toBeFalse();
});

it('connaît les douze clés du catalogue et ne verrouille que required et password_strong', function (): void {
    expect(ValidationRuleKey::cases())->toHaveCount(12)
        ->and(array_filter(ValidationRuleKey::cases(), static fn (ValidationRuleKey $key): bool => $key->isLocked()))->toBe([0 => ValidationRuleKey::Required, 9 => ValidationRuleKey::PasswordStrong])
        ->and(ValidationRuleKey::locked())->toBe([ValidationRuleKey::Required, ValidationRuleKey::PasswordStrong])
        ->and(ValidationRuleKey::forCases())->toContain(ValidationRuleKey::DateNotFuture, ValidationRuleKey::AgeRange, ValidationRuleKey::CommuneInZone);
});
