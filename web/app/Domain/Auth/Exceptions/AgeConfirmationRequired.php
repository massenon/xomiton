<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/** Q68 : la case « J'ai :min_age ans ou plus » doit être cochée (aucune date de naissance n'est collectée). */
final class AgeConfirmationRequired extends AuthException
{
    public function __construct()
    {
        parent::__construct('auth.age_confirmation_required', ['min_age' => (int) config('xomiton.accounts.min_age')]);
    }
}
