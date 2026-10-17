<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Support\Audit;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Journalise un verrouillage par limitation de débit sur la connexion (P2-02, P2-09) : identifiant et IP de la requête.
 */
final class LogLockout
{
    public function handle(Lockout $event): void
    {
        Audit::log('auth.lockout', null, sprintf('identifiant=%s ; ip=%s', $this->identifier($event->request), $event->request->ip() ?? '-'), null);
    }

    private function identifier(Request $request): string
    {
        foreach (LogFailedLogin::IDENTIFIER_KEYS as $key) {
            $value = $request->input($key);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return Str::limit(Str::lower(trim((string) $value)), 190, '');
            }
        }

        return '-';
    }
}
