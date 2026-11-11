<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

/** Dates de fenêtre impossibles : début passé, fin avant le début, durée au-delà du plafond (P13-06). */
final class MaintenanceWindowInvalid extends MaintenanceException
{
    /**
     * @param  'past'|'order'|'too_long'  $reason
     * @param  array<string, mixed>  $replace
     */
    public function __construct(string $reason, array $replace = [])
    {
        parent::__construct('maintenance.errors.window_'.$reason, $reason === 'past' ? 'starts_at' : 'ends_at', $replace);
    }

    public function errorCode(): string
    {
        return 'maintenance_window_invalid';
    }
}
