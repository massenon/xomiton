<?php

declare(strict_types=1);

use App\Domain\Alerts\Dispatch\RetryPolicy;

/*
 * P5-03 : délais croissants [1, 1, 2, 5, 10] min (le dernier se répète), plafond 10 tentatives, seuil du SMS de repli.
 */

it('calcule la prochaine reprise et s’arrête au plafond', function (): void {
    $policy = new RetryPolicy([1, 1, 2, 5, 10], 10);
    $now = new DateTimeImmutable('2026-10-18 14:00:00');
    $at = static fn (int $attempts): ?string => $policy->nextAttemptAt($attempts, $now)?->format('H:i');

    expect($at(1))->toBe('14:01')
        ->and($at(2))->toBe('14:01')
        ->and($at(3))->toBe('14:02')
        ->and($at(4))->toBe('14:05')
        ->and($at(5))->toBe('14:10')
        ->and($at(7))->toBe('14:10')
        ->and($at(10))->toBeNull()
        ->and($policy->isExhausted(10))->toBeTrue()
        ->and($policy->isExhausted(9))->toBeFalse();
});

it('déclenche le SMS de repli au seuil d’échecs push', function (): void {
    $policy = new RetryPolicy([1], 10);

    expect($policy->shouldFallbackToSms(2, 2))->toBeTrue()
        ->and($policy->shouldFallbackToSms(1, 2))->toBeFalse()
        ->and(fn () => new RetryPolicy([], 10))->toThrow(InvalidArgumentException::class);
});
