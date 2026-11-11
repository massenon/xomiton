<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Durée de vie GLISSANTE du jeton de l'application mobile selon l'espace le plus sensible du compte (D-MOB-9, plan §6.3,
 * recommandation retenue) : espace Admin (`admin_mobile.access`) 7 jours, espace pair 30 jours (`xomiton.accounts.mobile_token_days`) ;
 * un compte membre garde un jeton sans échéance propre. Chaque requête authentifiée par jeton Bearer repousse l'échéance
 * (`personal_access_tokens.expires_at`, honorée par Sanctum : un jeton resté inutilisé plus longtemps répond 401) ; au plus
 * une écriture par jour et par jeton. Les rôles changent sans reconnexion : l'échéance suit le rôle actuel (un jeton qui a
 * reçu l'espace Admin est ramené à 7 jours). Sans effet pour une session web (cookie). À poser après `auth:sanctum`.
 */
final class SlideMobileTokenExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        // Jeton enregistré seulement : une session web (jeton transitoire) n'a pas d'échéance à faire glisser.
        if ($token instanceof PersonalAccessToken && $token->exists) {
            $days = $this->days($user);

            if ($days !== null) {
                $target = now()->addDays($days);
                $expires = $token->expires_at;

                if ($expires === null || abs($expires->diffInSeconds($target, false)) > 86_400) {
                    $token->forceFill(['expires_at' => $target])->save();
                }
            }
        }

        return $next($request);
    }

    private function days(User $user): ?int
    {
        $days = (array) config('xomiton.accounts.mobile_token_days', ['admin' => 7, 'peer' => 30]);

        return match (true) {
            $user->can('admin_mobile.access') => isset($days['admin']) ? (int) $days['admin'] : null,
            $user->hasRole('pair') => isset($days['peer']) ? (int) $days['peer'] : null,
            default => null,
        };
    }
}
