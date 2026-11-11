<?php

declare(strict_types=1);

use App\Domain\Alerts\Exceptions\AcknowledgeLinkInvalid;
use App\Domain\Alerts\Exceptions\AlertClosed;
use App\Domain\Alerts\Exceptions\AlertException;
use App\Domain\Alerts\Exceptions\CancelWindowExpired;
use App\Domain\Alerts\Exceptions\ContactUnavailable;
use App\Domain\Alerts\Exceptions\InvalidStatusTransition;
use App\Domain\Alerts\Exceptions\NotAnSos;
use App\Domain\Shared\Exceptions\DomainException;
use App\Enums\AlertStatus;

/*
 * P5-02 : refus métier des cas d'usage — statut HTTP, code stable (API P5-06) et message français non vide.
 */

it('porte le statut, le code et un message pour chaque refus', function (AlertException $exception, int $status, string $code): void {
    expect($exception)->toBeInstanceOf(DomainException::class)
        ->and($exception->status())->toBe($status)
        ->and($exception->errorCode())->toBe($code)
        ->and($exception->userMessage())->not->toBe('')->not->toStartWith('alerts.')
        ->and($exception->getMessage())->toBe($exception->userMessage());
})->with([
    'transition' => fn () => [new InvalidStatusTransition(AlertStatus::Closed, AlertStatus::InProgress), 422, 'invalid_transition'],
    'close' => fn () => [new AlertClosed, 422, 'alert_closed'],
    'fenêtre' => fn () => [new CancelWindowExpired(60), 422, 'cancel_window_expired'],
    'non SOS' => fn () => [new NotAnSos, 422, 'not_sos'],
    'contact' => fn () => [new ContactUnavailable, 422, 'contact_unavailable'],
    'lien' => fn () => [new AcknowledgeLinkInvalid, 403, 'forbidden'],
]);

it('nomme les statuts et la fenêtre dans le message', function (): void {
    expect((new InvalidStatusTransition(AlertStatus::Closed, AlertStatus::InProgress))->userMessage())->toContain('Clos')->toContain('En cours')
        ->and((new CancelWindowExpired(60))->userMessage())->toContain('60');
});
