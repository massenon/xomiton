<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canal d'origine d'une alerte ou d'un dossier (DATABASE_DICTIONARY.md §5 `alerts.source`, §6 `cases.source`).
 * `annuaire` = demande de rappel depuis l'annuaire (écart prototype ↔ document résolu dans le dictionnaire).
 */
enum AlertSource: string
{
    case Web = 'web';
    case App = 'app';
    case SyncOffline = 'sync-offline';
    case Directory = 'annuaire';

    public function label(): string
    {
        return (string) __('enums/alerts.alert_source.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Web => 'primary',
            self::App => 'info',
            self::SyncOffline => 'secondary',
            self::Directory => 'violet',
        };
    }

    /** Créée hors connexion puis synchronisée : clé d'idempotence et appareil obligatoires. */
    public function isOffline(): bool
    {
        return $this === self::SyncOffline;
    }
}
