<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Exceptions;

/**
 * Clé d'idempotence déjà prise par une alerte d'un autre type (signalement rejoué en besoin…) : erreur du client, pas
 * un rejeu ADR-32. 409 `idempotency_key_reused`, comme le middleware `idempotent` pour une même clé et un autre corps
 * (P5-09). Un SOS ne lève jamais cette exception jusqu'au client (TriggerSos le crée avec une clé dérivée).
 */
final class IdempotencyKeyConflict extends AlertException
{
    public function __construct()
    {
        parent::__construct('alerts.errors.idempotency_conflict');
    }

    public function errorCode(): string
    {
        return 'idempotency_key_reused';
    }

    public function status(): int
    {
        return 409;
    }
}
