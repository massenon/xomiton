<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus métier d'une demande de rôle (D-MOB-15) : déjà pair, demande déjà en cours, compte inactif, e-mail non confirmé,
 * demande déjà traitée. Message de lang/fr/profile.php (`role_requests.errors.<raison>`), 422.
 */
final class RoleRequestRefused extends DomainException
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct('profile.role_requests.errors.'.$reason);
    }

    public function errorCode(): string
    {
        return $this->reason;
    }
}
