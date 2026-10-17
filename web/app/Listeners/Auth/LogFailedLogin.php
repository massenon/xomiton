<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Str;

/**
 * Journalise un échec de connexion (P2-09) : identifiant saisi seulement, jamais le mot de passe.
 * Le compte visé est rattaché quand il existe, mais l'acteur reste « anonyme » (personne n'est connectée).
 */
final class LogFailedLogin
{
    /**
     * Champs du formulaire ou des identifiants pouvant porter l'e-mail / le pseudo (partagés avec LogLockout).
     *
     * @var list<string>
     */
    public const IDENTIFIER_KEYS = ['login', 'email', 'pseudo', 'identifier', 'username'];

    public function handle(Failed $event): void
    {
        $subject = $event->user instanceof User ? $event->user : null;
        $identifier = $this->identifier($event->credentials);

        Audit::log(
            'auth.failed',
            $subject,
            sprintf('garde=%s ; identifiant=%s', $event->guard, $identifier ?? '-'),
            null,
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function identifier(array $credentials): ?string
    {
        foreach (self::IDENTIFIER_KEYS as $key) {
            if (isset($credentials[$key]) && is_scalar($credentials[$key])) {
                return Str::limit(Str::lower(trim((string) $credentials[$key])), 190, '');
            }
        }

        return null;
    }
}
