<?php

declare(strict_types=1);

use App\Domain\Account\ProgressiveProfile;
use App\Domain\Account\SensitiveProfile;

/*
 * Champs sensibles et profil progressif (P8-08) : règles pures — rien de sensible n'est divulgué sans choix explicite
 * (`shared_fields`) ni, pour le genre et l'orientation, sans consentement en cours ; un groupe à la fois.
 */

it('ne divulgue aucun champ sensible sans choix explicite', function (): void {
    $values = ['gender_identity' => 'femme_trans', 'orientation' => 'lesbienne', 'ally_status' => 'allie'];

    expect(SensitiveProfile::disclosed($values, null, true))->toBe([])
        ->and(SensitiveProfile::disclosed($values, [], true))->toBe([]);
});

it('divulgue les seuls champs partagés, le genre et l’orientation seulement sous consentement', function (): void {
    $values = ['gender_identity' => 'femme_trans', 'orientation' => 'lesbienne', 'ally_status' => 'allie'];

    expect(SensitiveProfile::disclosed($values, ['orientation', 'ally_status'], true))->toBe(['orientation' => 'lesbienne', 'ally_status' => 'allie'])
        ->and(SensitiveProfile::disclosed($values, ['orientation', 'ally_status'], false))->toBe(['ally_status' => 'allie'])
        ->and(SensitiveProfile::disclosed(['orientation' => 'prefer_not'], ['orientation'], true))->toBe([]);
});

it('nettoie shared_fields : champs connus, sans doublon, dans un ordre stable', function (): void {
    expect(SensitiveProfile::normalizeShared(['ally_status', 'trusted_contact_phone', 'gender_identity', 'ally_status', 3]))
        ->toBe(['gender_identity', 'ally_status']);
});

it('propose le premier groupe non traité et s’arrête quand tout est traité', function (): void {
    $state = ProgressiveProfile::state(['pronouns' => true], ['member_type', 'inconnu']);

    expect($state['next'])->toBe('ally_status')
        ->and($state['answered'])->toBe(2)
        ->and($state['total'])->toBe(8)
        ->and($state['done'])->toBeFalse()
        ->and(ProgressiveProfile::state([], ProgressiveProfile::GROUPS)['done'])->toBeTrue()
        ->and(ProgressiveProfile::groupOf('age_range'))->toBe('age')
        ->and(ProgressiveProfile::groupOf('commune_id'))->toBe('location');
});
