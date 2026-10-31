<?php

declare(strict_types=1);

use App\Http\Middleware\HandleConditionalGet;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Thematique;
use App\Models\User;
use App\Support\PublicContentCache;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * Performance des pages publiques (docs/PERFORMANCE.md §11) : cache HTTP des pages invitées (Cache-Control, ETag, 304),
 * aucune session ni cookie pour une personne sans cookie, requêtes SQL bornées, cache de données de l'accueil et son
 * invalidation, Speculation Rules (liste blanche, nonce CSP, aucune route sensible), transitions de vue, .htaccess.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

dataset('pagesInvitees', [
    'accueil' => ['/'],
    'confidentialité (légal)' => ['/politique-de-confidentialite'],
    'conditions' => ['/conditions-utilisation'],
    'mentions légales' => ['/mentions-legales'],
    'cookies' => ['/cookies'],
    'confidentialité & conditions' => ['/confidentialite'],
    'thématiques' => ['/thematiques'],
    'ressources' => ['/ressources'],
    'actualités' => ['/actualites'],
    'aide' => ['/aide'],
    'lois' => ['/lois'],
    'do-glegbe' => ['/do-glegbe'],
    'violences' => ['/violences'],
]);

dataset('pagesAvecSession', [
    'connexion' => ['/connexion'],
    'suppression de compte' => ['/supprimer-mon-compte'],
    'signaler' => ['/signaler'],
    'sos' => ['/sos'],
    'urgences' => ['/urgences'],
    'newsletter (formulaire)' => ['/newsletter'],
]);

it('sert une page invitée sans session ni cookie, cacheable (Cache-Control, ETag, Vary)', function (string $uri): void {
    config(['session.driver' => 'database']); // pilote de production : une session ouverte écrirait dans `sessions`

    $response = $this->get($uri)->assertOk();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Set-Cookie'))->toBeNull()
        ->and(DB::table('sessions')->count())->toBe(0)
        ->and($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=60')->toContain('stale-while-revalidate=300')
        ->and($response->headers->get('Cache-Control'))->not->toContain('private')->not->toContain('no-cache')->not->toContain('no-store')
        ->and($response->headers->get('ETag'))->not->toBeNull()
        ->and($response->headers->get('Vary'))->toContain('Cookie')
        ->and($response->headers->get('X-Xm-Audience'))->toBe('guest')
        ->and((string) $response->getContent())->not->toContain('name="_token"');
})->with('pagesInvitees');

it('répond 304 sans corps ni CSP à If-None-Match, avec ou sans le suffixe de compression d’Apache', function (): void {
    $first = $this->get('/cookies')->assertOk();
    $etag = (string) $first->headers->get('ETag');

    expect($etag)->toMatch('/^"[0-9a-f]{32}"$/');

    foreach ([$etag, 'W/'.$etag, rtrim($etag, '"').'-gzip"', '"autre", '.$etag] as $sent) {
        $response = $this->get('/cookies', ['If-None-Match' => $sent])->assertStatus(304);

        expect($response->getContent())->toBe('')
            ->and($response->headers->get('Cache-Control'))->toContain('public')
            ->and($response->headers->get('ETag'))->toBe($etag)
            ->and($response->headers->has('Content-Security-Policy'))->toBeFalse() // la copie du navigateur garde le sien
            ->and($response->headers->getCookies())->toBe([]);
    }

    $this->get('/cookies', ['If-None-Match' => '"périmé"'])->assertOk();
});

it('garde un ETag stable malgré un nonce CSP différent à chaque réponse', function (): void {
    $a = $this->get('/mentions-legales')->assertOk();
    $b = $this->get('/mentions-legales')->assertOk();

    preg_match("/'nonce-([^']+)'/", (string) $a->headers->get('Content-Security-Policy'), $na);
    preg_match("/'nonce-([^']+)'/", (string) $b->headers->get('Content-Security-Policy'), $nb);

    expect($na[1])->not->toBe($nb[1])
        ->and($a->headers->get('ETag'))->toBe($b->headers->get('ETag'))
        ->and((string) $a->getContent())->toContain('nonce="'.$na[1].'"')
        ->and((string) $a->getContent())->not->toContain($nb[1]);
});

it('ne change pas d’ETag quand le contenu change', function (): void {
    $before = $this->get('/')->headers->get('ETag');
    Thematique::factory()->create(['title' => 'Fiche nouvelle pour l’ETag']);
    $after = $this->get('/')->headers->get('ETag');

    expect($after)->not->toBe($before);
});

it('répond à HEAD avec les mêmes en-têtes de cache et sans cookie', function (): void {
    $response = $this->call('HEAD', '/cookies');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->getCookies())->toBe([]);
});

it('ne met jamais en cache une page servie à une session ou à un compte', function (string $uri): void {
    $response = $this->get($uri);

    expect($response->headers->get('Cache-Control'))->not->toContain('public');
})->with('pagesAvecSession');

it('suit le parcours normal, privé, dès qu’un cookie de session est présent', function (): void {
    $response = $this->withCookie((string) config('session.cookie'), 'cookie-existant')->get('/cookies')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public')
        ->and($response->headers->has('ETag'))->toBeFalse();
});

it('ne sert jamais la variante invitée à une personne connectée ni avec Authorization', function (): void {
    $user = User::factory()->create();

    // Un navigateur connecté envoie toujours son cookie de session (actingAs seul n'en envoie pas).
    foreach (['/', '/cookies', '/thematiques'] as $uri) {
        $response = $this->actingAs($user)->withCookie((string) config('session.cookie'), 'cookie-existant')->get($uri)->assertOk();

        expect($response->headers->get('Cache-Control'))->not->toContain('public')
            ->and($response->headers->get('X-Xm-Audience'))->toBeNull();
    }

    $this->withHeader('Authorization', 'Bearer x')->get('/cookies')->assertOk()
        ->assertHeaderMissing('ETag');
});

it('n’affiche pas de formulaire POST sans jeton CSRF dans le pied de page d’une page sans session', function (): void {
    $html = (string) $this->get('/cookies')->assertOk()->getContent();
    $footer = substr($html, (int) strpos($html, '<footer'));

    expect($footer)->not->toContain('action="'.route('newsletter.subscribe').'"')
        ->and($footer)->toContain('href="'.route('newsletter.show').'#inscription"');

    // La page /newsletter (session + jeton CSRF) garde son formulaire protégé ; le POST n'est pas exempté du jeton CSRF (bootstrap/app.php).
    $page = (string) $this->get('/newsletter')->assertOk()->getContent();
    expect($page)->toContain('action="'.route('newsletter.subscribe').'"')->toContain('name="_token"');
});

it('borne les requêtes SQL : deux (état et annonce de maintenance) sur les pages légales, cinq au plus sur l’accueil à froid, deux au chaud', function (): void {
    Thematique::factory()->count(3)->create();
    Faq::factory()->onHome()->count(3)->create();
    $count = function (string $uri): array {
        DB::enableQueryLog();
        $this->get($uri)->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::flushQueryLog();
        DB::disableQueryLog();

        return [$queries->count(), $queries->filter(fn (string $q): bool => str_contains($q, 'sessions'))->count()];
    };

    $this->get('/'); // chauffe les paramètres lus par le middleware de maintenance
    PublicContentCache::invalidate();

    [$cold, $coldSessions] = $count('/');
    [$warm, $warmSessions] = $count('/');
    [$legal, $legalSessions] = $count('/politique-de-confidentialite');

    expect($cold)->toBeLessThanOrEqual(5) // maintenance, FAQ, fiches, catégories (eager loading), annonce de maintenance
        ->and($warm)->toBeLessThanOrEqual(2) // maintenance : état + annonce, jamais de session ni de contenu
        ->and($legal)->toBeLessThanOrEqual(2)
        ->and($coldSessions + $warmSessions + $legalSessions)->toBe(0);
});

it('vide le cache de données de l’accueil à chaque modification de contenu', function (): void {
    $thematique = Thematique::factory()->create(['title' => 'Premier titre de fiche']);
    $faq = Faq::factory()->onHome()->create(['question' => 'Première question ?']);

    expect((string) $this->get('/')->getContent())->toContain('Premier titre de fiche')->toContain('Première question ?');

    // Chaud : aucune requête sur les contenus.
    DB::enableQueryLog();
    $this->get('/');
    $tables = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $q): bool => str_contains($q, 'thematiques') || str_contains($q, 'faqs'));
    DB::disableQueryLog();
    expect($tables)->toBeEmpty();

    $thematique->update(['title' => 'Titre de fiche modifié']);
    expect((string) $this->get('/')->getContent())->toContain('Titre de fiche modifié')->not->toContain('Premier titre de fiche');

    $faq->update(['question' => 'Question modifiée ?']);
    expect((string) $this->get('/')->getContent())->toContain('Question modifiée ?');

    $faq->delete();
    expect((string) $this->get('/')->getContent())->not->toContain('Question modifiée ?');

    Category::query()->whereKey($thematique->category_id)->update(['label' => 'Catégorie renommée']);
    Category::query()->find($thematique->category_id)?->touch();
    expect((string) $this->get('/')->getContent())->toContain('Catégorie renommée');
});

it('sert une image livrée avec l’application en WebP d’abord, JPEG en repli', function (): void {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toMatch('~<picture>\s*<source type="image/webp" srcset="[^"]*/05\.webp"[^>]*>\s*<img [^>]*src="[^"]*/05\.jpg"[^>]*fetchpriority="high"~');
    foreach (['assets/images/xomiton/05.webp', 'assets/images/xomiton/covers/th-1.webp', 'assets/images/xomiton/covers/th-1-320.webp'] as $file) {
        expect(public_path($file))->toBeFile();
    }
});

// --- Speculation Rules et navigation -------------------------------------------------------------------------------

/** @return array{rules: array<string, mixed>, nonce: string, html: string} */
function speculation(string $uri = '/'): array
{
    $response = test()->get($uri)->assertOk();
    $html = (string) $response->getContent();
    preg_match('#<script type="speculationrules" nonce="([^"]+)">(.*?)</script>#s', $html, $m);
    expect($m)->not->toBeEmpty();
    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $csp);

    return ['rules' => json_decode($m[2], true, 512, JSON_THROW_ON_ERROR), 'nonce' => $m[1], 'html' => $html, 'csp' => $csp[1]];
}

/** Motif URLPattern simplifié (`*` = toute suite de caractères) → expression régulière. */
function speculationPattern(string $pattern): string
{
    return '~^'.str_replace('\*', '.*', preg_quote($pattern, '~')).'$~';
}

it('déclare des Speculation Rules (prefetch moderate, prerender conservative) avec le nonce de la CSP', function (): void {
    ['rules' => $rules, 'nonce' => $nonce, 'csp' => $csp] = speculation('/');

    expect($nonce)->toBe($csp)
        ->and($rules['prefetch'][0]['eagerness'])->toBe('moderate')
        ->and($rules['prerender'][0]['eagerness'])->toBe('conservative')
        ->and($rules['prefetch'][0]['where'])->toBe($rules['prerender'][0]['where']);

    $and = $rules['prefetch'][0]['where']['and'];
    expect($and[0]['href_matches'])->toContain('/', '/politique-de-confidentialite', '/conditions-utilisation', '/mentions-legales', '/cookies')
        ->and($and[1]['not']['href_matches'])->toContain('/ressources/*/telecharger', '/ressources/*/lire')
        ->and($and[2]['not']['selector_matches'])->toContain('rel~=nofollow')->toContain('data-no-prefetch');
});

it('n’inclut aucune règle sur les routes sensibles, SOS, authentification, compte, API ou administration', function (): void {
    $allow = (array) config('xomiton.speculation.allow');
    $deny = (array) config('xomiton.speculation.exclude');
    $sensitive = ['sos*', 'signaler*', 'urgences*', 'connexion*', 'inscription*', 'deconnexion*', 'otp*', 'code-de-verification*', 'mot-de-passe-oublie*',
        'nouveau-mot-de-passe*', 'reinitialisation*', 'supprimer-mon-compte*', 'admin.*', 'api.*', 'fil*', 'messages*', 'profil*', 'compte*', 'parametres*',
        'newsletter.confirm*', 'newsletter.unsubscribe*', 'newsletter.subscribe', 'media.signed', 'alertes*', 'aide.feedback', 'content-event', 'violences'];

    $matched = [];
    foreach (Route::getRoutes() as $route) {
        $name = (string) $route->getName();
        $uri = '/'.ltrim(preg_replace('/\{[^}]+\}/', 'x', $route->uri()), '/');
        $reachable = collect($allow)->contains(fn (string $p): bool => preg_match(speculationPattern($p), $uri) === 1)
            && ! collect($deny)->contains(fn (string $p): bool => preg_match(speculationPattern($p), $uri) === 1);

        if ($reachable && ($route->methods()[0] !== 'GET' || collect($sensitive)->contains(fn (string $s): bool => Illuminate\Support\Str::is($s, $name)))) {
            $matched[] = $route->methods()[0].' '.$uri.' ('.$name.')';
        }
    }

    expect($matched)->toBe([]);

    foreach ($allow as $pattern) {
        expect($pattern)->not->toMatch('~^/(sos|signaler|urgences|connexion|inscription|deconnexion|admin|api|supprimer-mon-compte|fil|messages|profil|compte|violences)~');
    }
});

it('n’émet les règles ni sur les gabarits d’authentification et sobres (connexion, SOS, signalement, urgences)', function (string $uri): void {
    $html = (string) $this->get($uri)->getContent();

    expect($html)->not->toContain('speculationrules');
})->with(['/connexion', '/sos', '/signaler', '/urgences']);

it('se désactive par configuration', function (): void {
    config(['xomiton.speculation.enabled' => false]);

    expect((string) $this->get('/cookies')->getContent())->not->toContain('speculationrules');
});

it('active les transitions de vue inter-documents sauf mouvement réduit, en ligne et dans la feuille principale', function (): void {
    foreach (['resources/css/critical.css', 'resources/css/app/view-transitions.css'] as $file) {
        $css = (string) file_get_contents(base_path($file));

        expect($css)->toMatch('~@media \(prefers-reduced-motion: no-preference\)\s*\{[^}]*@view-transition\s*\{\s*navigation:\s*auto;\s*\}~s');
    }

    expect((string) file_get_contents(base_path('resources/css/xomiton.css')))->toContain('app/view-transitions.css');
});

it('livre les polices et le CSS critique : préchargement de Montserrat, CSS critique en ligne, JS en module différé', function (): void {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('<link rel="preload" as="font" type="font/woff2" crossorigin')
        ->and($html)->toMatch('~<style nonce="[^"]+">[^<]*@view-transition~')
        ->and($html)->toMatch('~<script type="module" src="[^"]*public-[^"]+\.js"~');
})->skip(fn (): bool => ! is_file(public_path('build/manifest.json')), 'public/build absent (npm run build)');

// --- .htaccess et Debugbar ------------------------------------------------------------------------------------------

it('configure dans .htaccess le cache longue durée des ressources versionnées et la compression', function (): void {
    $htaccess = (string) file_get_contents(public_path('.htaccess'));

    expect($htaccess)->toContain('RewriteRule ^build/ - [E=XM_IMMUTABLE:1]')
        ->and($htaccess)->toContain('Header set Cache-Control "public, max-age=31536000, immutable" env=XM_IMMUTABLE')
        ->and($htaccess)->toContain('RewriteRule ^assets/ - [E=XM_STATIC:1]')
        ->and($htaccess)->toContain('Header set Cache-Control "public, max-age=2592000, stale-while-revalidate=86400" env=XM_STATIC')
        ->and($htaccess)->toContain('DEFLATE text/html text/css')->toContain('BROTLI_COMPRESS text/html text/css')
        ->and($htaccess)->toContain('AddType image/webp .webp')->toContain('ExpiresByType image/webp')->toContain('ExpiresByType font/woff2 "access plus 1 year"');
});

it('ne charge jamais Debugbar hors environnement local avec APP_DEBUG=true', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['extra']['laravel']['dont-discover'])->toContain('barryvdh/laravel-debugbar')
        ->and($composer['require-dev'])->toHaveKey('barryvdh/laravel-debugbar')
        ->and($composer['require'])->not->toHaveKey('barryvdh/laravel-debugbar')
        ->and(app()->bound('debugbar'))->toBeFalse();

    $source = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));
    expect($source)->toContain("\$this->app->environment('local') && config('app.debug') === true");
});

it('applique l’en-tête ETag de HandleConditionalGet de la même façon (comparaison tolérante)', function (): void {
    expect(HandleConditionalGet::matches('"abc-gzip"', '"abc"'))->toBeTrue()
        ->and(HandleConditionalGet::matches('W/"abc"', '"abc"'))->toBeTrue()
        ->and(HandleConditionalGet::matches('"abd"', '"abc"'))->toBeFalse();
});
