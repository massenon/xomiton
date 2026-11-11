<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Enums\AllyRole;
use App\Enums\MemberType;

/**
 * Inscription en 3 étapes obligatoires (décision client du 11/11/2026), un seul envoi à la fin : toutes les données du
 * formulaire arrivent ensemble, déjà validées par App\Http\Requests\Support\RegistrationRules (web et API).
 *
 * Étape 1 : prénom, nom, pseudonyme, e-mail, âge (≥ `xomiton.accounts.min_age`, tient lieu de confirmation d'âge).
 * Étape 2 : pronoms, pays, ville, téléphone, zone → commune (facultatives, du pays choisi), « Vous êtes ? » (membre / allié·e).
 * Étape 3 : membre → identité de genre et orientation (clés de SensitiveProfile, ou `prefer_not`, `autre` + précision)
 * avec le consentement `sensitive_data` ; allié·e → statut (+ précision) ; mot de passe ; CGU et confidentialité, jamais
 * pré-cochées, horodatées dans `consents` avec IP et navigateur.
 */
final readonly class RegisterData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $pseudo,
        public string $email,
        public int $age,
        public string $pronouns,
        public string $countryCode,
        public MemberType $memberType,
        public string $password,
        public bool $acceptsTerms,
        public bool $acceptsPrivacy,
        public ?string $city = null,
        public ?string $phone = null,
        public ?int $zoneId = null,
        public ?int $communeId = null,
        public ?string $genderIdentity = null,
        public ?string $orientation = null,
        public ?string $genderIdentityOther = null,
        public ?string $orientationOther = null,
        public bool $acceptsSensitiveData = false,
        public ?AllyRole $allyRole = null,
        public ?string $allyRoleOther = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        // Lien / code d'invitation à l'équipe (pair, admin) : l'inscription reste celle d'un membre, le rôle suit à la validation.
        public ?string $invitationCode = null,
    ) {}

    public function isAlly(): bool
    {
        return $this->memberType === MemberType::Ally;
    }
}
