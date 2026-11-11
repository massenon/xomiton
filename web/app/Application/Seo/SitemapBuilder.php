<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Application\Community\Events\EventAccess;
use App\Application\Content\HelpCenter;
use App\Http\Controllers\Web\PlaceholderController;
use App\Models\Event;
use App\Models\Resource;
use App\Models\Thematique;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Plan du site natif (P4-11, SEO_CONTENT_GUIDE_INSTRUCTION.md §1) : seules les pages indexées y figurent, avec leur
 * `lastmod`. Décision : pas de spatie/laravel-sitemap — son crawler n'a pas sa place sur un hébergement mutualisé
 * (D7) et le contenu indexable est connu (routes publiques + contenus publiés en base). Le XML est mis en cache 1 h
 * (`xomiton.seo.sitemap_cache_seconds`, cache=file) ; `flush()` l'invalide, à appeler par la publication admin (P11).
 *
 * Jamais listés : l'administration, l'API, l'espace membre (routes `auth`, ADR-30), l'OTP, les pages de newsletter,
 * les résultats de recherche, les écrans provisoires (PlaceholderController, servis en noindex). Une route n'est retenue
 * que si elle existe, est publique (aucun middleware `auth`), n'est plus provisoire et hors des préfixes interdits :
 * chaque page apparaît quand sa phase la livre ; `/evenements` et les événements publics depuis P8-04 (Q35 a).
 *
 * Les `<loc>` partent de `config('app.url')`, jamais de l'hôte de la requête : le XML mis en cache sous une clé globale
 * ne peut pas être empoisonné par un en-tête Host (cohérent avec l'annonce Sitemap de robots.txt).
 */
final class SitemapBuilder
{
    public const CACHE_KEY = 'xomiton.sitemap.xml';

    /** @var list<string> Noms des routes publiques statiques candidates. */
    public const STATIC_ROUTES = [
        'home', 'thematiques.index', 'ressources.index', 'do-glegbe', 'violences', 'lois', 'aide.index', 'urgences',
        'actualites.index', 'confidentialite', 'legal.privacy', 'legal.terms', 'legal.notice', 'legal.cookies', 'account-deletion.show', 'newsletter.show', 'evenements.index', 'signaler', 'sos',
    ];

    /** @var list<string> Premiers segments d'URL jamais exposés. */
    public const EXCLUDED_PREFIXES = ['admin', 'api', 'membres', 'code-de-verification', 'recherche', 'storage', 'design-system'];

    /** @var list<string> Noms de routes de newsletter exclus (confirmation, désinscription, remerciement). */
    private const EXCLUDED_ROUTES = ['newsletter.confirm', 'newsletter.unsubscribe', 'newsletter.thanks', 'account-deletion.sent', 'account-deletion.confirm'];

    /**
     * XML complet, depuis le cache.
     */
    public function xml(): string
    {
        $ttl = max(60, (int) config('xomiton.seo.sitemap_cache_seconds', 3600));

        return Cache::remember(self::CACHE_KEY, $ttl, fn (): string => $this->render());
    }

    public function render(): string
    {
        return view('seo.sitemap', ['urls' => $this->urls()])->render();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * URL indexables, dédoublonnées, dans l'ordre : pages statiques puis contenus publiés.
     *
     * @return list<array{loc: string, lastmod: string|null}>
     */
    public function urls(): array
    {
        $urls = [];

        foreach (self::STATIC_ROUTES as $name) {
            if ($this->isPublicRoute($name)) {
                $urls[] = ['loc' => self::absolute($name), 'lastmod' => null];
            }
        }

        if ($this->isPublicRoute('thematiques.show')) {
            foreach (self::published(Thematique::query()) as $row) {
                $urls[] = ['loc' => self::absolute('thematiques.show', $row->slug), 'lastmod' => $row->updated_at?->toAtomString()];
            }
        }

        if ($this->isPublicRoute('ressources.show')) {
            foreach (self::published(Resource::query()->whereNot('kind', 'article')) as $row) {
                $urls[] = ['loc' => self::absolute('ressources.show', $row->slug), 'lastmod' => $row->updated_at?->toAtomString()];
            }
        }

        // Les actualités sont des ressources `kind = article` (DATABASE_DICTIONARY.md `resources`, Q1).
        if ($this->isPublicRoute('actualites.show')) {
            foreach (self::published(Resource::query()->news()) as $row) {
                $urls[] = ['loc' => self::absolute('actualites.show', $row->slug), 'lastmod' => $row->updated_at?->toAtomString()];
            }
        }

        // Centre d'aide (P4-05) : questions du périmètre HelpCenter (catégories `faq` actives, publiées).
        if ($this->isPublicRoute('aide.show')) {
            foreach (app(HelpCenter::class)->faqs()->get(['id', 'slug', 'updated_at']) as $row) {
                $urls[] = ['loc' => self::absolute('aide.show', $row->slug), 'lastmod' => $row->updated_at?->toAtomString()];
            }
        }

        // Événements publics à venir (P8-04, Q35 a) : `is_public`, publiés, hors groupe privé et page non approuvée.
        if ($this->isPublicRoute('evenements.show')) {
            $events = app(EventAccess::class)->forPublic(Event::query())->upcoming()->get(['events.id', 'events.ulid', 'events.updated_at']);

            foreach ($events as $row) {
                $urls[] = ['loc' => self::absolute('evenements.show', $row->ulid), 'lastmod' => $row->updated_at?->toAtomString()];
            }
        }

        $seen = [];

        return array_values(array_filter($urls, static function (array $url) use (&$seen): bool {
            if (isset($seen[$url['loc']]) || ! self::isAllowed($url['loc'])) {
                return false;
            }

            return $seen[$url['loc']] = true;
        }));
    }

    /**
     * URL absolue sur la racine canonique (`app.url`), indépendante de l'hôte et du schéma de la requête.
     */
    private static function absolute(string $name, ?string $slug = null): string
    {
        return rtrim((string) config('app.url'), '/').route($name, $slug === null ? [] : [$slug], false);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    private static function published(Builder $query): \Illuminate\Database\Eloquent\Collection
    {
        return $query->published()->orderByDesc('published_at')->orderBy('id')->get(['id', 'slug', 'updated_at']);
    }

    /**
     * La route nommée existe, est publique (aucun middleware `auth`, `auth:*`, `verified`, `can:*`), n'est plus servie
     * par PlaceholderController (écran provisoire en noindex) et n'est pas exclue.
     */
    private function isPublicRoute(string $name): bool
    {
        if (in_array($name, self::EXCLUDED_ROUTES, true)) {
            return false;
        }

        $route = Route::getRoutes()->getByName($name);

        if (! $route instanceof RouteInstance || ! in_array('GET', $route->methods(), true)) {
            return false;
        }

        if ($route->getControllerClass() === PlaceholderController::class) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (preg_match('/^(auth|verified|can)(:|$)/', $middleware) === 1) {
                return false;
            }
        }

        return self::isAllowed('/'.ltrim($route->uri(), '/'));
    }

    /**
     * Garde-fou final sur l'URL produite : aucun préfixe interdit, aucune page de newsletter secondaire.
     */
    private static function isAllowed(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = explode('/', $path);

        if (in_array($segments[0], self::EXCLUDED_PREFIXES, true)) {
            return false;
        }

        return ! ($segments[0] === 'newsletter' && count($segments) > 1);
    }
}
