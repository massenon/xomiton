<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

/**
 * Partie sensible et « profil progressif » du PATCH me/profile (P8-08), déjà validée par UpdateProfileRequest :
 * chaque champ nul est laissé tel quel ; `clear*` (réponse `prefer_not`) efface la valeur.
 */
final readonly class SensitiveProfileData
{
    /**
     * @param  list<string>|null  $sharedFields  champs sensibles montrés sur le profil (null : inchangé, [] : aucun)
     * @param  list<string>  $answeredGroups  groupes traités sans valeur (« Je préfère ne pas le dire »)
     * @param  bool|null  $showZone  « Afficher ma zone sur mon profil » du groupe Ville (null : inchangé)
     */
    public function __construct(
        public ?bool $consent = null,
        public ?string $genderIdentity = null,
        public bool $clearGenderIdentity = false,
        public ?string $orientation = null,
        public bool $clearOrientation = false,
        public ?array $sharedFields = null,
        public array $answeredGroups = [],
        public ?bool $showZone = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->consent === null && $this->genderIdentity === null && ! $this->clearGenderIdentity
            && $this->orientation === null && ! $this->clearOrientation && $this->sharedFields === null && $this->answeredGroups === []
            && $this->showZone === null;
    }
}
