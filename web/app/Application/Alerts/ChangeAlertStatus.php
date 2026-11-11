<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Support\AlertTransitions;
use App\Domain\Alerts\Events\AlertAcknowledged;
use App\Domain\Alerts\Exceptions\InvalidStatusTransition;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\User;
use App\Support\Audit;
use DateTimeImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Changement de statut par l'équipe (admin/alertes.php l.24-30, P10) : transitions fermées d'AlertStatus (`clos`
 * définitif), historique `alert_status_changes`, `closed_at` posé au passage à `clos`. Prendre en charge
 * (`pris_en_charge`, `en_cours`) une alerte sans accusé vaut accusé de réception (arrêt de l'escalade). Autorisation :
 * `take` pour prendre en charge, `close` pour traiter ou clore. Aucune note automatique : l'historique la remplace.
 */
final class ChangeAlertStatus
{
    /** Statuts qui valent prise en charge, donc accusé de réception s'il manque. */
    private const TAKING = [AlertStatus::Acknowledged, AlertStatus::InProgress];

    public function __invoke(Alert $alert, AlertStatus $to, User $by): Alert
    {
        Gate::forUser($by)->authorize(self::ability($to), $alert);

        $userId = (int) $by->getKey();

        /** @var array{0: Alert, 1: AlertStatus, 2: bool} $outcome */
        $outcome = AlertTransitions::locked($alert, static function (Alert $fresh) use ($to, $userId): array {
            $from = $fresh->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidStatusTransition($from, $to);
            }

            $acknowledges = in_array($to, self::TAKING, true) && ! $fresh->isAcknowledged();

            if ($acknowledges) {
                $fresh->acknowledged_at = now();
                $fresh->acknowledged_by = $userId;
            }

            AlertTransitions::move($fresh, $to, $userId);
            $fresh->save();

            return [$fresh, $from, $acknowledges];
        });

        [$fresh, $from, $acknowledged] = $outcome;

        Audit::log('alerts.status_changed', $fresh, sprintf('de=%s ; vers=%s', $from->value, $to->value), $by);

        if ($acknowledged) {
            event(new AlertAcknowledged(
                alertId: (int) $fresh->getKey(),
                type: $fresh->type->value,
                byUserId: $userId,
                contactId: null,
                via: AlertAcknowledged::VIA_ACCOUNT,
                occurredAt: new DateTimeImmutable,
            ));
        }

        AlertTransitions::statusChanged($fresh, $from, $to, $userId);

        return $fresh;
    }

    /** Capacité AlertPolicy exigée pour viser ce statut. */
    public static function ability(AlertStatus $to): string
    {
        return in_array($to, [AlertStatus::Handled, AlertStatus::Closed], true) ? 'close' : 'take';
    }
}
