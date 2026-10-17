<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Enums\MemberType;

/**
 * Phases 2 (profil) et 3 (sécurité) de l'inscription, toutes facultatives (« Passer ») : chaque champ nul est ignoré.
 */
final readonly class ProfileData
{
    public function __construct(
        public ?string $pronouns = null,
        public ?int $zoneId = null,
        public ?int $communeId = null,
        public ?MemberType $memberType = null,
        public ?string $trustedContactPhone = null,
        public ?string $trustedContactName = null,
        public ?bool $discreetMode = null,
    ) {}
}
