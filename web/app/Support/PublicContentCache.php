<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache de DONNÉES des pages publiques (page d'accueil : thématiques et FAQ), jamais de HTML : le HTML porte un nonce CSP
 * et des liens propres à la requête. Pilote de cache de l'application (`file` sur cPanel : ni Redis ni Memcached).
 *
 * Invalidation : une version globale est incorporée à chaque clé ; `invalidate()` l'incrémente, ce qui rend toutes les
 * entrées inaccessibles d'un coup (elles expirent d'elles-mêmes, sans balises de cache ni parcours du disque). Appelée à
 * chaque écriture sur Thematique, Faq ou Category (App\Observers\PublicContentObserver) et par les seeders. Le TTL borne
 * le reste (xomiton.http_cache.home_ttl_minutes, 10 minutes : une publication programmée, `published_at` futur, apparaît
 * au plus 10 minutes après son heure sans requête supplémentaire à chaque rendu).
 */
final class PublicContentCache
{
    private const VERSION_KEY = 'xm:public-content:version';

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public static function remember(string $name, Closure $resolve)
    {
        $key = 'xm:public-content:'.$name.':v'.self::version().':'.app()->getLocale();
        $hit = Cache::get($key);

        if ($hit !== null) {
            return $hit['value'];
        }

        $value = $resolve();

        // Enveloppe : une valeur légitimement vide (collection vide) reste un succès de cache.
        Cache::put($key, ['value' => $value], now()->addMinutes(max(1, (int) config('xomiton.http_cache.home_ttl_minutes', 10))));

        return $value;
    }

    public static function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
