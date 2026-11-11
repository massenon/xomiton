<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

/**
 * Compte créé par l'équipe (P12-01) : jamais de mot de passe saisi ni affiché, la personne le choisit par l'e-mail
 * `reinitialisation-mot-de-passe` (code et lien).
 */
final readonly class NewAccountData
{
    public function __construct(
        public string $name,
        public string $pseudo,
        public string $email,
        public string $role = 'membre',
        public ?string $phone = null,
        public ?int $zoneId = null,
        public bool $sendInvitation = true,
    ) {}
}
