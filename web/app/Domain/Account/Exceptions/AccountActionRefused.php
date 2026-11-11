<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus métier d'une action d'administration sur un compte (P12-01, RG-15) : soi-même, dernier superadmin, rôle non
 * attribuable, compte pas en attente… Le message vient de lang/fr/admin.php (`users.errors.<raison>`), 422.
 */
final class AccountActionRefused extends DomainException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(private readonly string $reason, array $replace = [])
    {
        parent::__construct('admin.users.errors.'.$reason, $replace);
    }

    public function errorCode(): string
    {
        return $this->reason;
    }
}
