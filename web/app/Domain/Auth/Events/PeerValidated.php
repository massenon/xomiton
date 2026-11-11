<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Compte pair éducateur·rice validé par l'administration (RG-03, FR-AUTH-05 ; P3-08). Émis par
 * App\Application\Auth\ValidatePeerAccount après la notification et le journal d'audit : identifiants seulement.
 */
final readonly class PeerValidated
{
    use Dispatchable;

    public function __construct(
        public int $peerId,
        public int $validatedById,
    ) {}
}
