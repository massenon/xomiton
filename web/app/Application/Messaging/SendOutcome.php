<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Models\Message;

/**
 * Résultat d'un envoi : le message (créé ou d'origine) et s'il vient d'être créé (201) ou rejoué (200).
 */
final readonly class SendOutcome
{
    public function __construct(
        public Message $message,
        public bool $created,
    ) {}
}
