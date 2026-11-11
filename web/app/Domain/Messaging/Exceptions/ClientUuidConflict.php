<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * `client_uuid` déjà pris dans la conversation par le message d'une autre personne : erreur du client (jamais 409,
 * ADR-32 : la boîte d'envoi considérerait l'envoi comme échoué sans raison) ; le message de l'autre n'est pas renvoyé.
 */
final class ClientUuidConflict extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.messages.api.client_uuid_conflict');
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
