<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Support\AlertTransitions;
use App\Domain\Alerts\Events\SosMarkedSafe;
use App\Domain\Alerts\Exceptions\AlertClosed;
use App\Domain\Alerts\Exceptions\NotAnSos;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\User;
use App\Support\Audit;
use DateTimeImmutable;

/**
 * « Je suis en sécurité » (signaler-et-sos.md l.17, simulateur.html l.905-913) : à tout moment tant que le SOS n'est
 * pas annulé ; pose `safe_at` (fin de l'escalade) et passe une alerte ouverte à `traite` — jamais `clos`, la clôture
 * revient à l'équipe (décision par défaut). Même règle d'identité que CancelSos. Idempotent.
 */
final class MarkSafe
{
    public function __invoke(Alert $alert, ?User $by, ?string $sosToken = null): Alert
    {
        if (! $alert->isSos()) {
            throw new NotAnSos;
        }

        AlertTransitions::ensureOwner($alert, $by, $sosToken);

        // SOS anonyme : aucun compte dans l'historique, comme dans le journal et l'événement (RG-01).
        $userId = $by === null || $alert->is_anonymous ? null : (int) $by->getKey();

        /** @var array{0: Alert, 1: bool, 2: AlertStatus|null} $outcome */
        $outcome = AlertTransitions::locked($alert, static function (Alert $fresh) use ($userId): array {
            if ($fresh->isCancelled()) {
                throw new AlertClosed;
            }

            if ($fresh->isSafe()) {
                return [$fresh, false, null];
            }

            $from = $fresh->status;
            $fresh->safe_at = now();
            // Passage direct à `traite` depuis tout statut ouvert : c'est la personne qui le déclare, hors matrice de l'équipe.
            $moved = $from->isOpen();

            if ($moved) {
                AlertTransitions::move($fresh, AlertStatus::Handled, $userId);
            }

            $fresh->save();

            return [$fresh, true, $moved ? $from : null];
        });

        [$fresh, $changed, $from] = $outcome;

        if (! $changed) {
            return $fresh;
        }

        $actorId = $userId;

        $fresh->is_anonymous
            ? Audit::logWithoutRequest('alerts.marked_safe', $fresh)
            : Audit::log('alerts.marked_safe', $fresh, null, $by);

        event(new SosMarkedSafe((int) $fresh->getKey(), $actorId, new DateTimeImmutable));

        if ($from !== null) {
            AlertTransitions::statusChanged($fresh, $from, AlertStatus::Handled, $actorId);
        }

        return $fresh;
    }
}
