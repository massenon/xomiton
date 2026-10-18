<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Geste refusé sur une personne du groupe (propriétaire, ou rôle égal ou supérieur : GroupMembershipRules::canManage).
 */
final class MemberNotManageable extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.groups.errors.not_manageable');
    }

    public function errorCode(): string
    {
        return 'group_member_not_manageable';
    }

    public function status(): int
    {
        return 403;
    }
}
