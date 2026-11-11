<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Support\AlertTransitions;
use App\Domain\Alerts\Events\SosCancelled;
use App\Domain\Alerts\Exceptions\AlertClosed;
use App\Domain\Alerts\Exceptions\CancelWindowExpired;
use App\Domain\Alerts\Exceptions\NotAnSos;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonInterface;
use DateTimeImmutable;

/**
 * Repentir après l'envoi d'un SOS (Q39 b, P5-02) : possible pendant `xomiton.alerts.cancel_window_seconds` (60 s) par
 * la personne à l'origine (compte propriétaire, ou jeton `X-Sos-Token` d'un SOS anonyme, Q52 b) ; l'alerte passe à `clos` (historique, `closed_at`, `cancelled_at`) et SosCancelled permet
 * de prévenir les personnes alertées qu'il s'agissait d'une fausse alerte. Déjà annulé : idempotent.
 */
final class CancelSos
{
    public function __invoke(Alert $alert, ?User $by, ?string $sosToken = null, ?CarbonInterface $now = null): Alert
    {
        if (! $alert->isSos()) {
            throw new NotAnSos;
        }

        AlertTransitions::ensureOwner($alert, $by, $sosToken);

        $now ??= now();
        // SOS anonyme : aucun compte dans l'historique, comme dans le journal et l'événement (RG-01).
        $userId = $by === null || $alert->is_anonymous ? null : (int) $by->getKey();
        $seconds = (int) config('xomiton.alerts.cancel_window_seconds', 60);

        /** @var array{0: Alert, 1: AlertStatus|null} $outcome */
        $outcome = AlertTransitions::locked($alert, static function (Alert $fresh) use ($now, $userId, $seconds): array {
            if ($fresh->isCancelled()) {
                return [$fresh, null];
            }

            if ($fresh->status === AlertStatus::Closed) {
                throw new AlertClosed;
            }

            if (! $fresh->canBeCancelledAt($now)) {
                throw new CancelWindowExpired($seconds);
            }

            $from = $fresh->status;
            $fresh->cancelled_at = $now;
            AlertTransitions::move($fresh, AlertStatus::Closed, $userId);
            $fresh->save();

            return [$fresh, $from];
        });

        [$fresh, $from] = $outcome;

        if ($from === null) {
            return $fresh;
        }

        $fresh->is_anonymous
            ? Audit::logWithoutRequest('alerts.cancelled', $fresh, 'motif=fausse_alerte')
            : Audit::log('alerts.cancelled', $fresh, 'motif=fausse_alerte', $by);

        event(new SosCancelled((int) $fresh->getKey(), $fresh->is_anonymous ? null : $userId, new DateTimeImmutable));
        AlertTransitions::statusChanged($fresh, $from, AlertStatus::Closed, $fresh->is_anonymous ? null : $userId);

        return $fresh;
    }
}
