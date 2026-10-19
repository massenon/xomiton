<?php

declare(strict_types=1);

namespace App\Domain\Cases\Exceptions;

/**
 * `client_uuid` déjà porté par le dossier ou l'étape d'un autre compte (ou d'un autre dossier) : erreur du client,
 * jamais 409 (ADR-32 : la boîte d'envoi croirait l'envoi échoué) ; l'élément de l'autre n'est jamais renvoyé.
 */
final class ClientUuidConflict extends CaseException
{
    public function __construct()
    {
        parent::__construct('cases.errors.client_uuid_conflict');
    }

    public function errorCode(): string
    {
        return 'client_uuid_conflict';
    }

    public function errors(): array
    {
        return ['client_uuid' => [$this->userMessage()]];
    }
}
