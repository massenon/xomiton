<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Support\AlertTransitions;
use App\Domain\Alerts\Events\AlertReassigned;
use App\Domain\Alerts\Exceptions\AlertClosed;
use App\Domain\Alerts\Exceptions\ContactUnavailable;
use App\Enums\AlertStatus;
use App\Enums\RoutingFallback;
use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\Audit;
use DateTimeImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Réattribution manuelle (admin/alertes.php l.31-38, P10) : nouveau contact actif, `routing_fallback = manual`, trace
 * `alert_escalations` (motif `manual`). L'accusé et l'escalade déjà posés sont conservés (décision par défaut :
 * l'équipe suit déjà l'alerte). Autorisation `reassign` (`alerts.manage`).
 */
final class ReassignAlert
{
    public function __invoke(Alert $alert, DirectoryContact $to, User $by): Alert
    {
        Gate::forUser($by)->authorize('reassign', $alert);

        if (! $to->is_active || $to->trashed()) {
            throw new ContactUnavailable;
        }

        $toId = (int) $to->getKey();

        /** @var array{0: Alert, 1: int|null, 2: bool} $outcome */
        $outcome = AlertTransitions::locked($alert, static function (Alert $fresh) use ($toId): array {
            if ($fresh->isCancelled() || $fresh->status === AlertStatus::Closed) {
                throw new AlertClosed;
            }

            $from = $fresh->routed_to === null ? null : (int) $fresh->routed_to;

            if ($from === $toId && $fresh->routing_fallback === RoutingFallback::Manual) {
                return [$fresh, $from, false];
            }

            $fresh->routed_to = $toId;
            $fresh->routing_fallback = RoutingFallback::Manual;
            $fresh->save();

            AlertEscalation::query()->create([
                'alert_id' => $fresh->getKey(),
                'from_contact_id' => $from,
                'to_contact_id' => $toId,
                'reason' => AlertEscalation::REASON_MANUAL,
            ]);

            return [$fresh, $from, true];
        });

        [$fresh, $from, $changed] = $outcome;

        if (! $changed) {
            return $fresh;
        }

        Audit::log('alerts.reassigned', $fresh, sprintf('de=%s ; vers=#%d', $from === null ? '-' : '#'.$from, $toId), $by);

        event(new AlertReassigned(
            alertId: (int) $fresh->getKey(),
            type: $fresh->type->value,
            fromContactId: $from,
            toContactId: $toId,
            byUserId: (int) $by->getKey(),
            occurredAt: new DateTimeImmutable,
        ));

        return $fresh;
    }
}
