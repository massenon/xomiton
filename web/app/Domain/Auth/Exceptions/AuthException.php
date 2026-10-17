<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

/**
 * Exception métier du tunnel d'authentification : `userMessage()` donne le texte à afficher (lang/fr/auth.php,
 * tutoiement, ou le message explicite fourni, par exemple celui configuré par l'administration), `status()` le code
 * HTTP conseillé aux contrôleurs et à l'API.
 */
abstract class AuthException extends RuntimeException
{
    /**
     * @param  array<string, scalar>  $replace
     */
    public function __construct(
        private readonly string $langKey,
        private readonly array $replace = [],
        private readonly ?string $explicitMessage = null,
    ) {
        parent::__construct($this->userMessage());
    }

    public function userMessage(): string
    {
        if ($this->explicitMessage !== null && trim($this->explicitMessage) !== '') {
            return $this->explicitMessage;
        }

        return (string) __($this->langKey, $this->replace);
    }

    public function status(): int
    {
        return 422;
    }
}
