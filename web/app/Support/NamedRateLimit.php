<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Consommation d'un limiteur nommé (AppServiceProvider::registerRateLimiters) depuis un contrôleur, après validation :
 * un formulaire refusé (consentement oublié, adresse mal saisie) ne brûle pas la tentative, contrairement au
 * middleware `throttle:<nom>`. Même seuil, même clé et même compteur que le middleware (clé `md5(nom.clé)`).
 * Limiteur à plusieurs plafonds (session et IP) : tous sont vérifiés avant d'en compter un, une demande refusée par
 * l'un ne consomme donc pas les autres.
 *
 *   if (($retryAfter = NamedRateLimit::attempt('newsletter', $request)) !== null) { … trop rapide … }
 */
final class NamedRateLimit
{
    private function __construct() {}

    /** Compte une tentative ; null si elle est acceptée, sinon le nombre de secondes avant la prochaine. */
    public static function attempt(string $name, Request $request): ?int
    {
        $limiter = RateLimiter::limiter($name);

        if ($limiter === null) {
            throw new InvalidArgumentException("Limiteur « {$name} » inconnu.");
        }

        $limits = $limiter($request);
        $counted = [];
        $retryAfter = null;

        foreach (is_array($limits) ? $limits : [$limits] as $limit) {
            if (! $limit instanceof Limit || $limit instanceof Unlimited || $limit->maxAttempts <= 0 || $limit->decaySeconds <= 0) {
                continue;
            }

            $key = md5($name.$limit->key);

            if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
                $retryAfter = max($retryAfter ?? 1, RateLimiter::availableIn($key));
            }

            $counted[$key] = $limit->decaySeconds;
        }

        if ($retryAfter !== null) {
            return $retryAfter;
        }

        foreach ($counted as $key => $decaySeconds) {
            RateLimiter::hit($key, $decaySeconds);
        }

        return null;
    }
}
