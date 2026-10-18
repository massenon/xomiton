<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus métier d'une action sur une alerte (messages lang/fr/alerts.php `errors.*`, statut HTTP 422 par défaut, code stable `errorCode()` rendu par ApiExceptionRenderer).
 */
abstract class AlertException extends DomainException {}
