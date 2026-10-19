<?php

declare(strict_types=1);

use App\Domain\Cases\Plausibility;
use App\Enums\AgeRange;
use Carbon\CarbonImmutable;

/*
 * Plausibilité (règle `plausibility`, P9-01) : RG-07 (date non future, avertissement au-delà de `max_past_years`),
 * RG-08 reformulée par Q25 a (tranche d'âge dans les bornes `age_min` / `age_max`), cadre GPS du Bénin.
 */

it('reprend les bornes du catalogue et retombe sur les valeurs par défaut si elles sont illisibles', function (): void {
    $custom = Plausibility::fromParams(['age_min' => 15, 'age_max' => 60, 'max_past_years' => 3, 'lat' => [6, 12], 'lng' => [1, 3]]);

    expect($custom->toArray())->toBe(['age_min' => 15, 'age_max' => 60, 'max_past_years' => 3, 'lat' => [6.0, 12.0], 'lng' => [1.0, 3.0]]);

    $broken = Plausibility::fromParams(['age_min' => 80, 'age_max' => 20, 'max_past_years' => 0, 'lat' => [12, 6], 'lng' => 'x']);

    expect($broken->toArray())->toBe((new Plausibility)->toArray())
        ->and(Plausibility::fromParams(null, 7)->maxPastYears)->toBe(7);
});

it('dit si une date est future par jour calendaire (RG-07)', function (): void {
    $today = CarbonImmutable::parse('2026-10-19 23:59:00');

    expect(Plausibility::isFuture(CarbonImmutable::parse('2026-10-19'), $today))->toBeFalse()
        ->and(Plausibility::isFuture(CarbonImmutable::parse('2026-10-20'), $today))->toBeTrue()
        ->and(Plausibility::isFuture(CarbonImmutable::parse('2020-01-01'), $today))->toBeFalse();
});

it('avertit au-delà de max_past_years ans, jamais avant', function (): void {
    $plausibility = new Plausibility(maxPastYears: 5);
    $today = CarbonImmutable::parse('2026-10-19');

    expect($plausibility->isTooOld(CarbonImmutable::parse('2021-10-19'), $today))->toBeFalse()
        ->and($plausibility->isTooOld(CarbonImmutable::parse('2021-10-18'), $today))->toBeTrue()
        ->and($plausibility->isTooOld(CarbonImmutable::parse('2026-01-01'), $today))->toBeFalse();
});

it('admet toutes les tranches de 10-14 à 50+ avec les bornes par défaut (Q25 a)', function (): void {
    expect((new Plausibility)->acceptedAgeRanges())->toBe(AgeRange::cases());
});

it('écarte une tranche qui déborde des bornes réglées', function (): void {
    $plausibility = new Plausibility(ageMin: 15, ageMax: 49);

    expect($plausibility->acceptsAgeRange(AgeRange::From10To14))->toBeFalse()
        ->and($plausibility->acceptsAgeRange(AgeRange::From15To17))->toBeTrue()
        ->and($plausibility->acceptsAgeRange(AgeRange::From35To49))->toBeTrue()
        ->and($plausibility->acceptsAgeRange(AgeRange::FiftyPlus))->toBeFalse();
});

it('vérifie que des coordonnées tombent dans le cadre du Bénin', function (): void {
    $plausibility = new Plausibility;

    expect($plausibility->acceptsCoordinates(6.37, 2.39))->toBeTrue()
        ->and($plausibility->acceptsCoordinates(48.85, 2.35))->toBeFalse()
        ->and($plausibility->acceptsCoordinates(6.37, 5.0))->toBeFalse();
});

it('donne les bornes de chaque tranche d’âge', function (): void {
    expect(AgeRange::From10To14->lowerBound())->toBe(10)
        ->and(AgeRange::From10To14->upperBound())->toBe(14)
        ->and(AgeRange::FiftyPlus->lowerBound())->toBe(50)
        ->and(AgeRange::FiftyPlus->upperBound())->toBeNull();
});
