<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/** Inscriptions fermées par l'administration (FR-AUTH-06) : le message configuré est affiché tel quel. */
final class RegistrationsClosed extends AuthException
{
    public function __construct(string $configuredMessage)
    {
        parent::__construct('auth.registrations_closed', [], $configuredMessage);
    }

    public function status(): int
    {
        return 403;
    }
}
