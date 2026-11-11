<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Niveau de routage retenu pour une alerte (DATABASE_DICTIONARY.md §5 `alerts.routing_fallback`, RG-04) : rôle demandé
 * dans le département, point focal du département, point focal national, premier contact actif ; `manual` quand une
 * personne a choisi le contact (demande de rappel depuis l'annuaire, réattribution par l'équipe), jamais RG-04.
 */
enum RoutingFallback: string
{
    case RoleZone = 'role_zone';
    case FocalZone = 'focal_zone';
    case FocalNational = 'focal_national';
    case FirstActive = 'first_active';
    case Manual = 'manual';

    public function label(): string
    {
        return (string) __('enums/alerts.routing_fallback.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::RoleZone => 'success',
            self::FocalZone => 'info',
            self::FocalNational => 'warning',
            self::FirstActive => 'danger',
            self::Manual => 'secondary',
        };
    }

    /** Niveau de repli de RG-04 (badge « repli » de l'aperçu du routage, MODULE_SPECIFICATIONS.md §P11). */
    public function isFallback(): bool
    {
        return ! in_array($this, [self::RoleZone, self::Manual], true);
    }

    /** Niveau 1 à 4 de RG-04 ; 0 pour une attribution manuelle. */
    public function level(): int
    {
        return match ($this) {
            self::RoleZone => 1,
            self::FocalZone => 2,
            self::FocalNational => 3,
            self::FirstActive => 4,
            self::Manual => 0,
        };
    }
}
