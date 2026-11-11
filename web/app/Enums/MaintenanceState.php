<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État affiché par l'écran Mode maintenance (P13-06) : inactif · annoncé · en cours · terminé (24 h après la fin).
 * Calculé par App\Application\Platform\MaintenanceOverview, jamais stocké.
 */
enum MaintenanceState: string
{
    case Inactive = 'inactive';
    case Announced = 'announced';
    case Ongoing = 'ongoing';
    case Ended = 'ended';

    public function label(): string
    {
        return (string) __('maintenance.admin.states.'.$this->value);
    }

    /** Couleur de badge (jamais seule : libellé et icône l'accompagnent). */
    public function badge(): string
    {
        return match ($this) {
            self::Inactive => 'success',
            self::Announced => 'warning',
            self::Ongoing => 'danger',
            self::Ended => 'primary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Inactive => 'check-circle',
            self::Announced => 'megaphone',
            self::Ongoing => 'cone-striped',
            self::Ended => 'flag',
        };
    }
}
