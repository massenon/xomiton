<?php

declare(strict_types=1);

namespace App\Application\Auth;

/**
 * Tentative de connexion : identifiant (e-mail ou pseudonyme, casse ignorée) et mot de passe.
 * `deviceCookie` est la valeur brute du cookie `remember_device` (Q13 b) ; absente pour l'API.
 */
final readonly class LoginData
{
    public function __construct(
        public string $identifier,
        public string $password,
        public bool $remember = false,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $deviceCookie = null,
    ) {}
}
