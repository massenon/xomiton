<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

/*
 * P5-02 : textes ajoutés par le lot (lang/fr/alerts.php, enums/alerts.php, validation.php), typographie française
 * (espace insécable avant « : ; ? ! ») et vouvoiement de la page d'accusé de réception.
 */

it('définit toutes les clés du lot', function (string $key): void {
    expect(Lang::has($key, 'fr', false))->toBeTrue($key)
        ->and(trans($key))->not->toBe('');
})->with([
    'alerts.errors.invalid_transition', 'alerts.errors.alert_closed', 'alerts.errors.cancel_window_expired', 'alerts.errors.not_sos',
    'alerts.errors.contact_unavailable', 'alerts.errors.idempotency_conflict', 'alerts.acknowledge.mine', 'alerts.acknowledge.reveal',
    'alerts.acknowledge.received_on', 'alerts.acknowledge.priority', 'alerts.acknowledge.confirm', 'alerts.acknowledge.closed', 'alerts.acknowledge.contact_title',
    'alerts.acknowledge.no_contact', 'alerts.acknowledge.preferred_channel', 'alerts.callback.default_message', 'alerts.callback.contact_unavailable',
    'alerts.report.fields.preferred_channel', 'alerts.report.channels.whatsapp',
    'enums/alerts.routing_fallback.manual', 'enums/alerts.contact_channel.call', 'enums/alerts.escalation_reason.no_ack_48h',
    'validation.phone_or_email', 'validation.attributes.occurred_on', 'validation.attributes.need',
]);

it('place une espace insécable avant les deux-points et vouvoie sur la page d’accusé', function (): void {
    $texts = array_merge(trans('alerts.errors'), trans('alerts.acknowledge'), trans('enums/alerts.escalation_reason'));

    foreach ($texts as $key => $text) {
        expect($text)->not->toMatch('/ [:;?!](?![a-z_])/u', (string) $key);
    }

    foreach (trans('alerts.acknowledge') as $key => $text) {
        expect($text)->not->toMatch('/\b(tu|ton|ta|tes|te)\b/iu', (string) $key);
    }
});
