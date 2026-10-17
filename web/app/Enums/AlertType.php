<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nature d'une alerte (DATABASE_DICTIONARY.md §5 `alerts.type`) : dénonciation d'une violence,
 * demande d'aide (besoin) ou SOS géolocalisé. Le SOS ne passe jamais par la file (DEVELOPMENT_STANDARDS.md §2).
 */
enum AlertType: string
{
    case Report = 'denonciation';
    case Need = 'besoin';
    case Sos = 'sos';

    public function label(): string
    {
        return (string) __('enums/alerts.alert_type.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Report => 'warning',
            self::Need => 'info',
            self::Sos => 'danger',
        };
    }

    /** Icône Bootstrap Icons du prototype (`xa_types_alert()`). */
    public function icon(): string
    {
        return match ($this) {
            self::Report => 'bi-megaphone',
            self::Need => 'bi-life-preserver',
            self::Sos => 'bi-geo-alt-fill',
        };
    }

    public function isSos(): bool
    {
        return $this === self::Sos;
    }
}
