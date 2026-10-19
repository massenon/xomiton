<?php

declare(strict_types=1);

namespace App\Domain\Cases\Exceptions;

/**
 * Même `client_uuid`, même compte, mais un **contenu différent** du dossier ou de l'étape d'origine (ADR-32, précision
 * P5-09) : 422 `idempotency_key_reused`, comme le middleware `idempotent` pour une même clé et un autre corps. Le
 * rejeu n'est pas pris pour un doublon : la boîte d'envoi annonce le refus au lieu de retirer la saisie sans bruit.
 */
final class ClientUuidReused extends CaseException
{
    public function __construct()
    {
        parent::__construct('cases.errors.client_uuid_reused');
    }

    public function errorCode(): string
    {
        return 'idempotency_key_reused';
    }

    public function errors(): array
    {
        return ['client_uuid' => [$this->userMessage()]];
    }
}
