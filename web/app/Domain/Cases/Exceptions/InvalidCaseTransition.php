<?php

declare(strict_types=1);

namespace App\Domain\Cases\Exceptions;

use App\Enums\CaseStatus;

/** Changement de statut hors de CaseStatus::allowedTransitions() (un dossier archivé ne change plus de statut). */
final class InvalidCaseTransition extends CaseException
{
    public function __construct(public readonly CaseStatus $from, public readonly CaseStatus $to)
    {
        parent::__construct('cases.errors.invalid_transition', ['from' => $from->label(), 'to' => $to->label()]);
    }

    public function errorCode(): string
    {
        return 'invalid_transition';
    }

    public function errors(): array
    {
        return ['status' => [$this->userMessage()]];
    }
}
