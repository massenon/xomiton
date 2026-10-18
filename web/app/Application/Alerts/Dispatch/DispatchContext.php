<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Domain\Alerts\Dispatch\DeliveryBudget;
use Carbon\CarbonImmutable;

/**
 * Contexte d'un envoi d'alerte : dans une requête (SOS, signalement, accusé), l'e-mail est seulement déposé dans la
 * boîte d'envoi et les remises externes tiennent dans le budget de la requête (D7) ; au cron (escalade, rappel), l'e-mail
 * part directement et le budget est celui de la minute.
 */
final readonly class DispatchContext
{
    private function __construct(
        public bool $deferMail,
        public DeliveryBudget $budget,
        public CarbonImmutable $now,
    ) {}

    public static function request(): self
    {
        return new self(true, RetryPolicyFactory::requestBudget(), CarbonImmutable::now());
    }

    public static function console(?CarbonImmutable $now = null): self
    {
        return new self(false, RetryPolicyFactory::consoleBudget(), $now ?? CarbonImmutable::now());
    }
}
