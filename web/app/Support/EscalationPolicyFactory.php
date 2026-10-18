<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Alerts\EscalationPolicy;
use App\Enums\AlertType;
use Throwable;

/**
 * Construit la politique RG-05 depuis config/xomiton.php `alerts.escalation` (délais par type, Q27), chaque délai
 * pouvant être surchargé par le réglage `alerts.escalation.<type>.<clé>` (P13-04 les exposera ; aucun n'est seedé).
 * Le domaine reste pur : seule cette fabrique lit la configuration. Dans App\Support (et non App\Application) pour que
 * le modèle Alert puisse s'en servir sans dépendre de la couche Application (ADR-29).
 */
final class EscalationPolicyFactory
{
    private const KEYS = ['remind_after_minutes', 'escalate_after_minutes'];

    private function __construct() {}

    public static function fromConfig(): EscalationPolicy
    {
        $config = (array) config('xomiton.alerts.escalation', []);

        foreach (AlertType::cases() as $type) {
            $values = is_array($config[$type->value] ?? null) ? $config[$type->value] : [];

            foreach (self::KEYS as $key) {
                $override = self::setting("alerts.escalation.{$type->value}.{$key}");

                if ($override !== null) {
                    $values[$key] = $override;
                }
            }

            $config[$type->value] = $values;
        }

        /** @var array<string, array{remind_after_minutes?: int|string|null, escalate_after_minutes?: int|string|null}> $config */
        return EscalationPolicy::fromArray($config);
    }

    private static function setting(string $key): ?int
    {
        try {
            $value = Settings::get($key);
        } catch (Throwable) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
