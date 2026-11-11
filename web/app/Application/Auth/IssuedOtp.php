<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Models\OtpCode;

/**
 * Code fraîchement émis : la valeur en clair n'existe qu'ici, le temps de composer l'e-mail (jamais journalisée).
 */
final readonly class IssuedOtp
{
    public function __construct(
        public OtpCode $record,
        public string $code,
    ) {}
}
