<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Action d'administration à fort impact sans étape de confirmation valable (API admin mobile) : 403 `step_up_required`.
 * Le client appelle `POST admin/step-up` (mot de passe), puis rejoue l'action avec l'en-tête `X-Step-Up-Token`.
 */
final class StepUpRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('admin.users.errors.step_up_required');
    }

    public function errorCode(): string
    {
        return 'step_up_required';
    }

    public function status(): int
    {
        return 403;
    }
}
