<?php

declare(strict_types=1);

namespace App\Domain\Sync;

/**
 * Qui l'emporte quand un même champ a changé sur le serveur et sur l'appareil depuis le dernier tirage
 * (SOFTWARE_ARCHITECTURE.md §5, P9-06).
 */
enum FieldOwner: string
{
    /** Statut, attribution : la valeur du serveur est gardée, la modification de l'appareil est écartée. */
    case Server = 'server';

    /** Notes : l'appareil l'emporte si son compte en est l'auteur·rice, sinon conflit. */
    case Author = 'author';

    /** Tout autre champ : conflit 409 enregistré dans `sync_conflicts`, tranché par l'administration. */
    case Shared = 'shared';
}
