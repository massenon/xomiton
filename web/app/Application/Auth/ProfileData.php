<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Enums\AgeRange;
use App\Enums\AllyStatus;
use App\Enums\MemberType;

/**
 * Phases 2 (profil) et 3 (sécurité) de l'inscription, toutes facultatives (« Passer ») : chaque champ nul est ignoré.
 */
final readonly class ProfileData
{
    /**
     * @param  list<string>|null  $interests  slugs de `newsletter_interests` (déjà validés)
     */
    public function __construct(
        public ?string $pronouns = null,
        public ?int $zoneId = null,
        public ?int $communeId = null,
        public ?MemberType $memberType = null,
        public ?string $trustedContactPhone = null,
        public ?string $trustedContactName = null,
        public ?bool $discreetMode = null,
        // Profil progressif de l'API (PATCH me/profile, P6-06) : `interests` [] vide les centres d'intérêt ;
        // `clearAllyStatus` efface le statut allié·e / ressource (valeur « aucun »).
        public ?AgeRange $ageRange = null,
        public ?array $interests = null,
        public ?AllyStatus $allyStatus = null,
        public bool $clearAllyStatus = false,
    ) {}
}
