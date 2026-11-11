<?php

declare(strict_types=1);

namespace App\Domain\Referentials\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus métier d'une action sur un référentiel administrable (décision client du 11/11/2026) : élément encore utilisé
 * (dossiers, alertes, profils, gardes, annuaire…), zone qui a encore des communes, pays figé par l'usage. Le message
 * vient de lang/fr/admin_referentials.php (`errors.<raison>`) et propose de désactiver l'élément plutôt que de le supprimer.
 */
final class ReferentialRefused extends DomainException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(private readonly string $reason, array $replace = [])
    {
        parent::__construct('admin_referentials.errors.'.$reason, $replace);
    }

    public function errorCode(): string
    {
        return $this->reason;
    }
}
