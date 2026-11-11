<?php

declare(strict_types=1);

use App\Application\Alerts\Dispatch\DeliveryError;
use App\Domain\Alerts\Dispatch\NotifiedState;
use App\Domain\Alerts\Dispatch\RowSnapshot;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationPurpose;

/*
 * P5-03 : état agrégé d'une personne prévenue (vu / SMS envoyé / envoyé / en attente), objets de notification,
 * masquage des numéros dans les erreurs consignées.
 */

dataset('rows', [
    'push envoyé' => [[['push', 'sent', null]], NotifiedState::Sent],
    'push en échec puis SMS' => [[['push', 'failed', null], ['sms', 'sent', null]], NotifiedState::SmsSent],
    'accusé de réception' => [[['database', 'sent', null], ['sms', 'sent', '2026-10-18 14:03:21']], NotifiedState::Seen],
    'push en attente' => [[['push', 'pending', null]], NotifiedState::Pending],
    'push abandonné' => [[['push', 'skipped', null]], NotifiedState::Pending],
    'aucune ligne' => [[], NotifiedState::Pending],
    'le SMS prime sur le push' => [[['sms', 'sent', null], ['push', 'sent', null]], NotifiedState::SmsSent],
    'e-mail déposé' => [[['mail', 'sent', null]], NotifiedState::Sent],
]);

it('agrège l’état d’une personne', function (array $rows, NotifiedState $expected): void {
    $snapshots = array_map(static fn (array $row): RowSnapshot => new RowSnapshot($row[0], $row[1], $row[2] === null ? null : new DateTimeImmutable($row[2])), $rows);

    expect(NotifiedState::fromRows($snapshots))->toBe($expected)
        ->and($expected->label())->not->toBe('')->not->toStartWith('alerts.');
})->with('rows');

it('ne rejoue que les objets qui demandent une action et a un libellé pour chacun', function (): void {
    expect(array_map(static fn (NotificationPurpose $p): string => $p->value, NotificationPurpose::retryable()))
        ->toBe(['sos_triggered', 'alert_routed', 'alert_reminder', 'alert_escalated', 'sos_unacknowledged']);

    foreach (NotificationPurpose::cases() as $purpose) {
        expect(class_exists($purpose->notificationClass()))->toBeTrue($purpose->value)
            ->and($purpose->label())->not->toStartWith('enums/');
    }

    expect(NotificationPurpose::SosCancelled->isSos())->toBeTrue()
        ->and(NotificationPurpose::AlertRouted->isSos())->toBeFalse()
        ->and(DeliveryStatus::Skipped->isFinal())->toBeTrue()
        ->and(DeliveryStatus::Skipped->label())->toBe('Abandonnée')
        ->and(DeliveryStatus::Failed->isFinal())->toBeFalse();
});

it('masque les numéros dans les messages d’erreur consignés', function (): void {
    expect(DeliveryError::mask('Numéro +229 01 97 38 88 47 refusé'))->toBe('Numéro [masqué] refusé')
        ->and(DeliveryError::mask('HTTP 500'))->toBe('HTTP 500')
        ->and(DeliveryError::fromThrowable(new RuntimeException('Destinataire 0197388847 inconnu')))->toBe('RuntimeException : Destinataire [masqué] inconnu')
        ->and(mb_strlen(DeliveryError::mask(str_repeat('x', 400))))->toBe(255);
});
