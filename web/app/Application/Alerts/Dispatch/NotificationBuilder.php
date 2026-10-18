<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Enums\AlertType;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Notifications\AlertRouted;
use App\Notifications\AlertStatusChanged;
use App\Notifications\SosAcknowledged;
use App\Notifications\SosCancelled;
use App\Notifications\SosSafe;
use App\Notifications\SosTriggered;
use App\Notifications\SosUnacknowledged;
use App\Notifications\XomitonNotification;
use Carbon\CarbonImmutable;

/**
 * Construit la notification de la Phase 3 correspondant à un objet (`NotificationPurpose`), pour une personne : mêmes
 * textes, même discrétion, lien propre au destinataire (AlertLinks). Sert à l'envoi et au rejeu (le lien signé expiré
 * est régénéré). Ne lit jamais la position ni le récit : le lieu est un libellé (commune, sinon département).
 */
final class NotificationBuilder
{
    /**
     * @param  array<string, mixed>  $extra  `has_location` (bool), `minutes` (int), `by_label` (string), `status_label` (string)
     */
    public function for(NotificationPurpose $purpose, Alert $alert, RecipientTarget $target, array $extra = []): XomitonNotification
    {
        $id = (int) $alert->getKey();
        $code = self::code($alert);
        $url = AlertLinks::for($alert, $target);

        return match ($purpose) {
            NotificationPurpose::SosTriggered => new SosTriggered(
                alertId: $id,
                code: $code,
                place: self::hasLocation($alert, $extra) ? $alert->placeLabel() : null,
                zoneLabel: $alert->zoneLabel(),
                pseudo: $alert->is_anonymous ? null : $alert->authorLabel(),
                url: $url,
                acknowledgeable: AlertLinks::canAcknowledge($alert, $target),
            ),
            NotificationPurpose::AlertRouted, NotificationPurpose::AlertReminder, NotificationPurpose::AlertEscalated => new AlertRouted(
                alertId: $id,
                reference: $code,
                typeLabel: $alert->type instanceof AlertType ? $alert->type->label() : (string) $alert->type,
                zoneLabel: $alert->zoneLabel() ?? '—',
                receivedAt: $alert->created_at ?? CarbonImmutable::now(),
                url: $url,
                reminder: $purpose === NotificationPurpose::AlertReminder,
            ),
            NotificationPurpose::SosUnacknowledged => new SosUnacknowledged(
                alertId: $id,
                code: $code,
                minutes: (int) ($extra['minutes'] ?? self::minutesSince($alert)),
                zoneLabel: $alert->zoneLabel(),
                url: $url,
            ),
            NotificationPurpose::SosCancelled => new SosCancelled($id, $code, $target->hasAccount() ? $url : null),
            NotificationPurpose::SosSafe => new SosSafe($id, $code, $target->hasAccount() ? $url : null),
            NotificationPurpose::SosAcknowledged => new SosAcknowledged(
                alertId: $id,
                code: $code,
                byLabel: (string) ($extra['by_label'] ?? __('alerts.notified.kind.team')),
                url: $url,
            ),
            NotificationPurpose::AlertStatusChanged => new AlertStatusChanged(
                alertId: $id,
                code: $code,
                statusLabel: (string) ($extra['status_label'] ?? $alert->status->label()),
                url: $url,
            ),
        };
    }

    public static function code(Alert $alert): string
    {
        return $alert->tracking_code !== null && $alert->tracking_code !== '' ? $alert->displayCode() : (string) $alert->ulid;
    }

    /**
     * Position partagée ? Donnée par l'événement dans la requête ; au rejeu, présence de la colonne chiffrée (sans la
     * déchiffrer : seule sa présence compte).
     *
     * @param  array<string, mixed>  $extra
     */
    private static function hasLocation(Alert $alert, array $extra): bool
    {
        if (array_key_exists('has_location', $extra)) {
            return (bool) $extra['has_location'];
        }

        return $alert->getRawOriginal('lat') !== null;
    }

    private static function minutesSince(Alert $alert): int
    {
        return $alert->created_at === null ? 0 : max(0, (int) $alert->created_at->diffInMinutes(now(), true));
    }
}
