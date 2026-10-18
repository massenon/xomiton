<?php

declare(strict_types=1);

namespace App\Application\Alerts\Queries;

use App\Domain\Alerts\TrackingCode;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Suivi sans compte par code (FR-ALT-01, P5-06 `GET signalements/{code}`) : saisie tolérante (minuscules, espaces,
 * préfixe « SIG- »), alerte non annulée et, si elle est close, depuis moins de `xomiton.alerts.tracking_visible_days`
 * jours ; sinon null (réponse neutre « code inconnu », sans dire pourquoi). Lecture seule (ADR-29).
 */
final class FindAlertByTrackingCode
{
    public function __invoke(string $input): ?Alert
    {
        $code = TrackingCode::normalize($input);

        if ($code === null) {
            return null;
        }

        $visibleSince = now()->subDays((int) config('xomiton.alerts.tracking_visible_days', 90));

        return Alert::query()
            ->where('tracking_code', $code)
            ->whereNull('cancelled_at')
            ->where(fn (Builder $query) => $query->whereNull('closed_at')->orWhere('closed_at', '>', $visibleSince))
            ->with([
                'routedContact',
                'statusChanges' => fn (HasMany $changes) => $changes->orderBy('created_at')->orderBy('id'),
            ])
            ->first();
    }
}
