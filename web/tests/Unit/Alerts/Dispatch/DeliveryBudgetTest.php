<?php

declare(strict_types=1);

use App\Domain\Alerts\Dispatch\DeliveryBudget;
use App\Domain\Notifications\HttpRequestSpec;

/*
 * P5-03 (D7) : 3 s au plus par fournisseur, budget global de la requête ; horloge injectée, aucune attente réelle.
 */

function clockAt(float &$now): Closure
{
    return static function () use (&$now): float {
        return $now;
    };
}

it('borne le délai HTTP par le délai fournisseur et le budget restant, jamais sous 0,1 s', function (): void {
    $now = 100.0;
    $budget = DeliveryBudget::start(3.0, 8.0, clockAt($now));

    expect($budget->httpTimeout())->toBe(3.0)
        ->and($budget->remaining())->toBe(8.0);

    $now = 106.5;
    expect($budget->httpTimeout())->toBe(1.5)->and($budget->exhausted())->toBeFalse();

    $now = 108.2;
    expect($budget->exhausted())->toBeTrue()
        ->and($budget->remaining())->toBe(0.0)
        ->and($budget->httpTimeout())->toBe(DeliveryBudget::MIN_HTTP_TIMEOUT);
});

it('ferme la fenêtre d’un fournisseur une fois son délai écoulé depuis le début du groupe', function (): void {
    $now = 0.0;
    $budget = DeliveryBudget::start(3.0, 8.0, clockAt($now));
    $groupStart = $budget->now();

    $now = 2.9;
    expect($budget->providerWindowOpen($groupStart))->toBeTrue();

    $now = 3.0;
    expect($budget->providerWindowOpen($groupStart))->toBeFalse()
        ->and($budget->elapsed())->toBe(3.0);
});

it('borne un délai fournisseur plus long que le budget par le budget restant', function (): void {
    $now = 0.0;
    $budget = DeliveryBudget::start(10.0, 2.0, clockAt($now));

    expect($budget->httpTimeout())->toBe(2.0);
});

it('décrit une requête poolable immuable', function (): void {
    $spec = new HttpRequestSpec('POST', 'https://push.test/1', ['TTL' => '60'], json: ['title' => 'Alerte SOS']);

    expect($spec->method)->toBe('POST')->and($spec->json)->toBe(['title' => 'Alerte SOS'])->and($spec->body)->toBeNull()
        ->and(fn () => $spec->url = 'https://ailleurs.test')->toThrow(Error::class);
});
