<?php

declare(strict_types=1);

namespace App\Domain\Directory;

/**
 * Portée de l'annuaire membre `/annuaire` (P8-12 ; ROLE_ET_PERMISSIONS.md §3, ligne « Annuaire dynamique » ; Q19) selon
 * la personne connectée :
 *  - `none` : sans `directory.view` (aucune fiche) ;
 *  - `public` : membre, et compte pair·e en attente de validation (RG-03 : reste « membre ») — fiches `is_public` seules ;
 *  - `network` : pair·e validé·e, modération, administration — fiches non publiques comprises (le réseau d'orientation).
 * Toujours des fiches actives et non supprimées ; les notes et le compte lié ne sortent jamais de l'administration. La
 * demande de rappel ne vise qu'une fiche publique (Q51), quelle que soit la portée.
 */
enum DirectoryReach: string
{
    case None = 'none';
    case Public = 'public';
    case Network = 'network';

    public static function of(bool $canView, bool $activeAccount, bool $inNetwork): self
    {
        if (! $canView) {
            return self::None;
        }

        return $activeAccount && $inNetwork ? self::Network : self::Public;
    }

    public function canBrowse(): bool
    {
        return $this !== self::None;
    }

    /** Fiches non publiques visibles (réservées au réseau). */
    public function seesNonPublic(): bool
    {
        return $this === self::Network;
    }

    public function canRequestCallback(bool $contactIsPublic): bool
    {
        return $this->canBrowse() && $contactIsPublic;
    }
}
