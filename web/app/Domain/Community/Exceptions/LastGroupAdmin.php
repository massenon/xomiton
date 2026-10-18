<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Départ refusé : la personne est la dernière admin du groupe (GroupMembershipRules::canLeave).
 */
final class LastGroupAdmin extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.groups.errors.last_admin');
    }

    public function errorCode(): string
    {
        return 'group_last_admin';
    }
}
