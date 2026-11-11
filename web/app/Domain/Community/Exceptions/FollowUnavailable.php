<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Abonnement impossible (P8-06) : compte inconnu, soi-même, inactif, sans accès au réseau, blocage, ou profil réservé à
 * ses connexions ou privé (App\Domain\Community\NetworkRules::followable). Un seul message et un seul code : la réponse
 * ne révèle ni un blocage ni l'existence d'un compte.
 */
final class FollowUnavailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.follow.errors.follow_unavailable');
    }

    public function errorCode(): string
    {
        return 'follow_unavailable';
    }
}
