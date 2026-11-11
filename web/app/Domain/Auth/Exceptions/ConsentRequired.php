<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/** CGU + charte et confidentialité doivent être acceptées explicitement (cases distinctes, jamais pré-cochées). */
final class ConsentRequired extends AuthException
{
    public function __construct()
    {
        parent::__construct('auth.consent_required');
    }
}
