<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Exceptions;

/**
 * Suivi par code sans compte (`GET signalements/{code}`, FR-ALT-01) : code mal formé, inconnu, annulé ou clos depuis
 * plus de `xomiton.alerts.tracking_visible_days` jours. Réponse **neutre** : 404 `not_found` avec un seul message, sans
 * dire laquelle de ces raisons s'applique (le limiteur `tracking` borne l'énumération).
 */
final class TrackingCodeNotFound extends AlertException
{
    public function __construct()
    {
        parent::__construct('alerts.report.tracking.not_found', ['days' => (int) config('xomiton.alerts.tracking_visible_days', 90)]);
    }

    public function errorCode(): string
    {
        return 'not_found';
    }

    public function status(): int
    {
        return 404;
    }
}
