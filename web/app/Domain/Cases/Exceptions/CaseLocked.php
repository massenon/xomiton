<?php

declare(strict_types=1);

namespace App\Domain\Cases\Exceptions;

/**
 * Dossier archivé ou anonymisé : lecture seule (ni modification, ni nouvelle étape de suivi). Le désarchivage
 * relève de l'administration (P11-01).
 */
final class CaseLocked extends CaseException
{
    public function __construct()
    {
        parent::__construct('cases.errors.locked');
    }

    public function errorCode(): string
    {
        return 'case_locked';
    }
}
