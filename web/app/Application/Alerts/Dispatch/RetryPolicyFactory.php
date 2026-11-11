<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Domain\Alerts\Dispatch\DeliveryBudget;
use App\Domain\Alerts\Dispatch\RetryPolicy;

/**
 * Politique de rejeu et budgets lus dans `config/xomiton.php` (`alerts.*`) à chaque exécution : le domaine ne lit
 * jamais la configuration (ADR-29).
 */
final class RetryPolicyFactory
{
    private function __construct() {}

    public static function fromConfig(): RetryPolicy
    {
        $backoff = array_values(array_filter(
            array_map('intval', (array) config('xomiton.alerts.retry_backoff_minutes', [1, 1, 2, 5, 10])),
            static fn (int $minutes): bool => $minutes > 0,
        ));

        return new RetryPolicy($backoff === [] ? [1] : $backoff, max(1, (int) config('xomiton.alerts.max_retry_attempts', 10)));
    }

    /** Requête HTTP (SOS, signalement) : 3 s par fournisseur, 8 s pour l'ensemble (D7). */
    public static function requestBudget(): DeliveryBudget
    {
        return DeliveryBudget::start(self::providerTimeout(), (float) config('xomiton.alerts.dispatch_budget_seconds', 8));
    }

    /** Cron (`notifications:retry`, escalade) : doit finir avant la minute suivante. */
    public static function consoleBudget(): DeliveryBudget
    {
        return DeliveryBudget::start(self::providerTimeout(), (float) config('xomiton.alerts.retry_budget_seconds', 50));
    }

    private static function providerTimeout(): float
    {
        return (float) config('xomiton.alerts.provider_timeout_seconds', 3);
    }
}
