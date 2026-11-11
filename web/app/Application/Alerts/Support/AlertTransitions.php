<?php

declare(strict_types=1);

namespace App\Application\Alerts\Support;

use App\Domain\Alerts\Events\AlertStatusChanged;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\User;
use Closure;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Outils communs aux cas d'usage qui modifient une alerte existante : relecture sous verrou (`lockForUpdate`) dans une
 * transaction, ligne d'historique pour tout changement de statut (IMPLEMENTATION_PLAN l.112) et événement
 * AlertStatusChanged émis après le commit.
 */
final class AlertTransitions
{
    private function __construct() {}

    /**
     * @template TResult
     *
     * @param  Closure(Alert): TResult  $callback  reçoit l'alerte relue et verrouillée
     * @return TResult
     */
    public static function locked(Alert $alert, Closure $callback): mixed
    {
        return DB::transaction(static function () use ($alert, $callback): mixed {
            $fresh = Alert::query()->whereKey($alert->getKey())->lockForUpdate()->firstOrFail();

            return $callback($fresh);
        });
    }

    /** Passe l'alerte au statut voulu (sans sauvegarder) et écrit la ligne d'historique. */
    public static function move(Alert $alert, AlertStatus $to, ?int $userId): void
    {
        $from = $alert->status;
        $alert->status = $to;

        if ($to === AlertStatus::Closed && $alert->closed_at === null) {
            $alert->closed_at = now();
        }

        AlertStatusChange::query()->create([
            'alert_id' => $alert->getKey(),
            'user_id' => $userId,
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }

    public static function statusChanged(Alert $alert, ?AlertStatus $from, AlertStatus $to, ?int $byUserId): void
    {
        event(new AlertStatusChanged(
            alertId: (int) $alert->getKey(),
            type: $alert->type->value,
            from: $from?->value,
            to: $to->value,
            byUserId: $byUserId,
            authorId: $alert->is_anonymous ? null : $alert->user_id,
            occurredAt: new DateTimeImmutable,
        ));
    }

    /**
     * Action de la personne à l'origine d'un SOS (annulation, « Je suis en sécurité ») : le compte propriétaire si
     * l'alerte en a un ; pour un SOS anonyme, le jeton `X-Sos-Token` rendu à la création (Q52 b, SosOwnerToken).
     *
     * @throws AuthorizationException
     */
    public static function ensureOwner(Alert $alert, ?User $by, ?string $sosToken = null): void
    {
        $allowed = $alert->user_id !== null
            ? $by !== null && (int) $by->getKey() === (int) $alert->user_id
            : SosOwnerToken::matches($alert, $sosToken);

        if (! $allowed) {
            throw new AuthorizationException((string) __('auth.api.forbidden'));
        }
    }
}
