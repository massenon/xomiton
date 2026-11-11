<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/** Action réservée aux comptes dont l'e-mail est confirmé (publier, commenter, écrire, rejoindre un groupe). */
final class EmailNotVerified extends AuthException
{
    public function __construct()
    {
        parent::__construct('auth.email_not_verified');
    }

    public function status(): int
    {
        return 403;
    }
}
