<?php

declare(strict_types=1);

namespace App\Application\Mail\Exceptions;

use RuntimeException;

/**
 * Erreur de rendu d'un template e-mail (slug inconnu, template désactivé) : jamais exposée telle quelle à l'écran.
 */
abstract class TemplateException extends RuntimeException
{
    public function __construct(public readonly string $slug, string $message)
    {
        parent::__construct($message);
    }
}
