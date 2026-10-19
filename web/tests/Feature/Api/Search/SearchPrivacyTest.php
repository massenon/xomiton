<?php

declare(strict_types=1);

use App\Models\Resource;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures as F;

/*
 * Recherche membre (P8-13) : **aucun historique serveur**. Un terme cherché depuis la page `/recherche` (chargement
 * complet, visite Inertia, sans compte), l'API `search` et `search/suggestions` (session SPA et jeton) n'est écrit dans
 * aucune table — sessions comprises, en clair pour l'occasion (`_previous.url`, `url.intended`) — ni dans aucun message
 * de journal, même quand la base échoue (la QueryException recopierait la requête et ses liaisons) ou que la
 * maintenance coupe la recherche (503 rendu avant `auth`, session fermée puis régénérée) ; Referer réduit à l'origine.
 */

const SECRET_TERM = 'xqzvsecretmot';

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    // Sessions en base et en clair : si l'adresse `?q=` y entrait, le test la lirait.
    config(['session.driver' => 'database', 'session.encrypt' => false]);

    $this->logged = [];
    $this->contexts = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event): void {
        $this->contexts[] = $event->context;
        $this->logged[] = $event->message.' '.print_r(array_map(static fn (mixed $value): string => $value instanceof Throwable
            ? $value->getMessage().' '.$value->getTraceAsString().' '.($value->getPrevious()?->getMessage() ?? '')
            : (string) json_encode($value), $event->context), true);
    });

    $this->viewer = F::member();
    Resource::factory()->create(['title' => 'Fiche de départ']);
});

/**
 * Nouvelle session pour la requête suivante, comme un nouveau processus PHP : sans cela, le gestionnaire de sessions en
 * base partagé par les requêtes du test croit la ligne déjà créée et n'écrit pas les suivantes.
 */
function freshSession(): void
{
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    app('auth')->forgetGuards();
}

/**
 * Tables (sessions décodées) dont une ligne contient le terme.
 *
 * @return list<string>
 */
function tablesContaining(string $term): array
{
    $found = [];

    foreach (Schema::getTableListing() as $table) {
        $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

        foreach (DB::table($table)->get() as $row) {
            $text = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($table === 'sessions') {
                $text .= ' '.base64_decode((string) $row->payload);
            }

            if (stripos((string) $text, $term) !== false) {
                $found[] = $table;
            }
        }
    }

    return array_values(array_unique($found));
}

it('n’écrit le terme ni en base ni en journal : page complète, visite Inertia, API SPA et jeton, suggestions', function (): void {
    $requests = [
        fn () => $this->actingAs($this->viewer)->get('/recherche?q='.SECRET_TERM)->assertOk(),
        fn () => $this->actingAs($this->viewer)->get('/recherche?type=ressources&q='.SECRET_TERM)->assertOk(),
        fn () => $this->actingAs($this->viewer)->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get('/recherche?q='.SECRET_TERM),
        fn () => $this->actingAs($this->viewer)->withHeaders(['Referer' => 'http://localhost/fil'])
            ->getJson('/api/v1/search?q='.SECRET_TERM)->assertOk(),
        fn () => $this->actingAs($this->viewer)->withHeaders(['Referer' => 'http://localhost/fil'])
            ->getJson('/api/v1/search/suggestions?q='.SECRET_TERM)->assertOk(),
        fn () => F::as($this->viewer)->getJson('/api/v1/search?type=membres&q='.SECRET_TERM)->assertOk(),
    ];

    foreach ($requests as $request) {
        freshSession();
        $request();
    }

    expect(DB::table('sessions')->count())->toBeGreaterThanOrEqual(5)
        ->and(tablesContaining(SECRET_TERM))->toBe([])
        ->and(implode("\n", $this->logged))->not->toContain(SECRET_TERM);
});

it('ne garde pas l’adresse de la recherche dans la session d’une visite sans compte (url.intended)', function (): void {
    freshSession();
    $this->get('/recherche?q='.SECRET_TERM)->assertRedirect(route('connexion'));
    freshSession();
    $this->withHeaders(['Referer' => 'http://localhost/fil'])->getJson('/api/v1/search?q='.SECRET_TERM)->assertUnauthorized();

    expect(DB::table('sessions')->count())->toBe(2)
        ->and(tablesContaining(SECRET_TERM))->toBe([])
        ->and(implode("\n", $this->logged))->not->toContain(SECRET_TERM);
});

it('ne journalise pas le terme quand la base échoue (503 côté API, message dans la page), seulement classe et SQLSTATE', function (): void {
    Schema::drop('thematiques');

    F::as($this->viewer)->getJson('/api/v1/search?q='.SECRET_TERM)->assertStatus(503);
    // Garde `web` explicite : Sanctum::actingAs (F::as) a fait de `sanctum` la garde par défaut du test.
    $this->actingAs($this->viewer, 'web')->get('/recherche?q='.SECRET_TERM)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('failed', true));

    // Signalée des deux côtés (API par le gestionnaire d'exceptions, page par report()), avec l'indice d'exploitation.
    $hints = array_values(array_filter($this->contexts, static fn (array $context): bool => array_key_exists('cause', $context)));

    expect($this->logged)->not->toBeEmpty()
        ->and(implode("\n", $this->logged))->not->toContain(SECRET_TERM)
        ->and(implode("\n", $this->logged))->toContain(__('search.errors.unavailable'))
        ->and($hints)->toHaveCount(2)
        ->and(array_column($hints, 'cause'))->each->toBe(QueryException::class)
        ->and(array_column($hints, 'sqlstate'))->each->toBeString()->not->toBeEmpty();
});

it('n’écrit le terme dans aucune table quand la maintenance coupe la recherche (503 avant `auth`)', function (): void {
    Settings::set('maintenance', true);

    $requests = [
        // Membre connecté·e : déconnecté·e, session invalidée puis régénérée (EnsureNotInMaintenance).
        fn () => $this->actingAs($this->viewer)->get('/recherche?q='.SECRET_TERM)->assertStatus(503)
            ->assertHeader('Referrer-Policy', 'strict-origin'),
        fn () => $this->get('/recherche?type=membres&q='.SECRET_TERM)->assertStatus(503),
        fn () => $this->actingAs($this->viewer)->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get('/recherche?q='.SECRET_TERM)->assertStatus(503),
        fn () => $this->actingAs($this->viewer)->withHeaders(['Referer' => 'http://localhost/fil'])
            ->getJson('/api/v1/search/suggestions?q='.SECRET_TERM)->assertStatus(503),
    ];

    foreach ($requests as $request) {
        freshSession();
        $request();
    }

    expect(DB::table('sessions')->count())->toBeGreaterThanOrEqual(2)
        ->and(tablesContaining(SECRET_TERM))->toBe([])
        ->and(implode("\n", $this->logged))->not->toContain(SECRET_TERM);
});

it('masque le terme dans les dumps et refuse de le sérialiser', function (): void {
    $term = App\Domain\Search\SearchTerm::of(SECRET_TERM);

    expect(print_r($term, true))->not->toContain(SECRET_TERM)
        ->and(var_export($term->__debugInfo(), true))->not->toContain(SECRET_TERM)
        ->and(fn () => serialize($term))->toThrow(LogicException::class);
});
