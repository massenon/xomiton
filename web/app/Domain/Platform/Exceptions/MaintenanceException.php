<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus d'une action de l'écran Mode maintenance (P13-06) : 422, message de lang/fr/maintenance.php (errors.*),
 * erreur rattachée au champ concerné pour l'écran Livewire et pour une réponse JSON.
 */
abstract class MaintenanceException extends DomainException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(string $langKey, private readonly string $field = 'maintenance', array $replace = [])
    {
        parent::__construct($langKey, $replace);
    }

    public function field(): string
    {
        return $this->field;
    }

    public function errors(): array
    {
        return [$this->field => [$this->userMessage()]];
    }
}
