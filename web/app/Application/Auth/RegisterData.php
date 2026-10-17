<?php

declare(strict_types=1);

namespace App\Application\Auth;

/**
 * Phase 1 de l'inscription (D4, Q12 c) : la seule obligatoire. Les cases CGU + charte et confidentialité sont
 * distinctes et jamais pré-cochées ; leur acceptation est horodatée dans `consents` avec IP et navigateur.
 */
final readonly class RegisterData
{
    public function __construct(
        public string $pseudo,
        public string $email,
        public string $password,
        public bool $acceptsTerms,
        public bool $acceptsPrivacy,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $phone = null,
    ) {}
}
