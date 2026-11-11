<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Origine d'une sauvegarde (DATABASE_DICTIONARY.md §10 `backups.kind`) : lancée à la main depuis l'écran
 * « Mises à jour & optimisation », produite par la tâche planifiée, ou copie de sûreté prise avant une restauration.
 */
enum BackupKind: string
{
    case Manual = 'manuel';
    case Auto = 'auto';
    case BeforeRestore = 'avant_restauration';

    public function label(): string
    {
        return (string) __('enums/platform.backup_kind.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Manual => 'primary',
            self::Auto => 'info',
            self::BeforeRestore => 'warning',
        };
    }

    /**
     * Suffixe du nom de dossier du prototype (`AAAAMMJJ-HHMMSS[-suffixe]`) ; une sauvegarde manuelle n'en a pas.
     */
    public function suffix(): ?string
    {
        return match ($this) {
            self::Manual => null,
            self::Auto => 'auto',
            self::BeforeRestore => 'avant',
        };
    }

    /**
     * Sauvegarde dont la suppression est irréversible du point de vue de l'administration (copie de sûreté exclue).
     */
    public function isDeletable(): bool
    {
        return $this !== self::BeforeRestore;
    }

    /**
     * Nom de dossier du prototype (`20261017-153000`, `20261017-153000-avant`).
     */
    public static function fromPrototypeName(string $name): self
    {
        return match (true) {
            str_ends_with($name, '-avant') => self::BeforeRestore,
            str_ends_with($name, '-auto') => self::Auto,
            default => self::Manual,
        };
    }
}
