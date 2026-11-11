<?php

declare(strict_types=1);

namespace App\Domain\Cases\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus métier d'une action sur un dossier ou une étape de suivi (messages lang/fr/cases.php `errors.*`, au tu ;
 * statut 422 par défaut, code stable `errorCode()` rendu par ApiExceptionRenderer).
 */
abstract class CaseException extends DomainException {}
