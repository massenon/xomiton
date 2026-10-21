<?php

declare(strict_types=1);

use App\Application\Shared\SosBurstWatch;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use App\Notifications\SosBurstDetected;
use App\Support\KeyedHash;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Fondation de /api/v1 (P5-09, ADR-31) : enveloppe `{ data, meta?, links? }` d'ApiController, découpage de
 * routes/api.php en un groupe public anonyme (limiteurs nommés sur les écritures) et un groupe auth:sanctum,
 * limiteur `sos` jamais bloquant mais journalisé (ARCH §4).
 */

final class ExtrasTestResource extends JsonResource
{
    public function with(Illuminate\Http\Request $request): array
    {
        return ['meta' => ['version' => '1', 'source' => 'resource'], 'jsonapi' => ['ok' => true]];
    }
}

final class EnvelopeTestController extends ApiController
{
    public function extras(): Illuminate\Http\JsonResponse
    {
        return $this->respond(ExtrasTestResource::make(['id' => 9])->additional(['included' => [['id' => 1]], 'links' => ['related' => '/x']]), meta: ['source' => 'test']);
    }

    public function item(): Illuminate\Http\JsonResponse
    {
        return $this->respond(JsonResource::make(['id' => 7, 'label' => 'Écoute']), meta: ['source' => 'test'], links: ['self' => '/api/v1/_test/item']);
    }

    public function store(): Illuminate\Http\JsonResponse
    {
        return $this->created(['id' => 8], '/api/v1/_test/item/8');
    }

    public function pages(): Illuminate\Http\JsonResponse
    {
        $paginator = new LengthAwarePaginator([['id' => 3], ['id' => 4]], 5, 2, 2, ['path' => url('/api/v1/_test/pages')]);

        return $this->paginated($paginator, JsonResource::class);
    }

    public function cursor(): Illuminate\Http\JsonResponse
    {
        $paginator = new CursorPaginator([['id' => 1], ['id' => 2], ['id' => 3]], 2, null, ['path' => url('/api/v1/_test/cursor'), 'parameters' => ['id']]);

        return $this->paginated($paginator, JsonResource::class);
    }

    public function empty(): Symfony\Component\HttpFoundation\Response
    {
        return $this->noContent();
    }
}

/** Routes de routes/api.php (hors routes de test). */
function apiRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1') && ! str_contains($route->uri(), '_test'))
        ->values()
        ->all();
}

/** Route du groupe public : sans auth:sanctum (ADR-31). */
function isPublicApiRoute(RoutingRoute $route): bool
{
    return ! in_array('auth:sanctum', $route->gatherMiddleware(), true);
}

it('enveloppe une Resource dans { data, meta, links }', function (): void {
    Route::middleware('api')->get('api/v1/_test/item', [EnvelopeTestController::class, 'item']);

    $this->getJson('/api/v1/_test/item')
        ->assertOk()
        ->assertExactJson([
            'data' => ['id' => 7, 'label' => 'Écoute'],
            'meta' => ['source' => 'test'],
            'links' => ['self' => '/api/v1/_test/item'],
        ]);
});

it('répond 201 avec Location, 204 sans corps, et omet meta et links vides', function (): void {
    Route::middleware('api')->post('api/v1/_test/item', [EnvelopeTestController::class, 'store']);
    Route::middleware('api')->delete('api/v1/_test/item', [EnvelopeTestController::class, 'empty']);

    $this->postJson('/api/v1/_test/item')
        ->assertCreated()
        ->assertHeader('Location', '/api/v1/_test/item/8')
        ->assertExactJson(['data' => ['id' => 8]]);

    $this->deleteJson('/api/v1/_test/item')->assertNoContent();
});

it('pagine par page avec meta (page, total) et links (first, last, prev, next)', function (): void {
    Route::middleware('api')->get('api/v1/_test/pages', [EnvelopeTestController::class, 'pages']);

    $this->getJson('/api/v1/_test/pages')
        ->assertOk()
        ->assertJsonPath('data', [['id' => 3], ['id' => 4]])
        ->assertJsonPath('meta', ['current_page' => 2, 'last_page' => 3, 'per_page' => 2, 'total' => 5])
        ->assertJsonPath('links.first', url('/api/v1/_test/pages').'?page=1')
        ->assertJsonPath('links.last', url('/api/v1/_test/pages').'?page=3')
        ->assertJsonPath('links.prev', url('/api/v1/_test/pages').'?page=1')
        ->assertJsonPath('links.next', url('/api/v1/_test/pages').'?page=3');
});

it('fusionne with() et additional() de la Resource, meta et links passés à respond() l’emportant', function (): void {
    Route::middleware('api')->get('api/v1/_test/extras', [EnvelopeTestController::class, 'extras']);

    $this->getJson('/api/v1/_test/extras')
        ->assertOk()
        ->assertExactJson([
            'data' => ['id' => 9],
            'meta' => ['version' => '1', 'source' => 'test'],
            'links' => ['related' => '/x'],
            'jsonapi' => ['ok' => true],
            'included' => [['id' => 1]],
        ]);
});

it('garde les filtres de la requête dans les liens de pagination', function (): void {
    Route::middleware('api')->get('api/v1/_test/pages', [EnvelopeTestController::class, 'pages']);

    $response = $this->getJson('/api/v1/_test/pages?zone=3&page=2')->assertOk();

    expect($response->json('links.next'))->toBe(url('/api/v1/_test/pages').'?zone=3&page=3')
        ->and($response->json('links.first'))->toBe(url('/api/v1/_test/pages').'?zone=3&page=1');
});

it('pagine par curseur avec next_cursor et links', function (): void {
    Route::middleware('api')->get('api/v1/_test/cursor', [EnvelopeTestController::class, 'cursor']);

    $response = $this->getJson('/api/v1/_test/cursor')->assertOk();

    expect($response->json('data'))->toBe([['id' => 1], ['id' => 2]])
        ->and($response->json('meta.per_page'))->toBe(2)
        ->and($response->json('meta.next_cursor'))->toBeString()->not->toBeEmpty()
        ->and($response->json('meta.prev_cursor'))->toBeNull()
        ->and($response->json('links.next'))->toContain('cursor=')
        ->and($response->json('links.prev'))->toBeNull();
});

it('découpe routes/api.php en un groupe public et un groupe auth:sanctum sans changer les routes existantes', function (): void {
    $public = ['api.health', 'api.referentials.zones', 'api.referentials.violence_types', 'api.referentials.needs',
        'api.auth.register', 'api.auth.login', 'api.auth.otp.send', 'api.auth.otp.verify', 'api.auth.password.forgot', 'api.auth.password.reset'];
    $authenticated = ['api.auth.logout', 'api.me.show', 'api.me.profile.update', 'api.me.settings.update', 'api.me.sessions.index', 'api.me.sessions.destroy'];

    foreach ($public as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull($name)->and(isPublicApiRoute($route))->toBeTrue("{$name} doit rester public");
    }

    foreach ($authenticated as $name) {
        expect(Route::getRoutes()->getByName($name)?->gatherMiddleware())->toContain('auth:sanctum');
    }

    expect(Route::getRoutes()->getByName('api.auth.logout')?->uri())->toBe('api/v1/auth/logout')
        ->and(Route::getRoutes()->getByName('api.me.show')?->uri())->toBe('api/v1/me');
});

it('impose un limiteur nommé à chaque écriture publique, et auth:sanctum à toute famille hors ADR-31', function (): void {
    // `assistant` : assistant public de l'accueil (P4-01, Q41 a), ajouté aux familles publiques d'ADR-31.
    // `status` : état public de la plateforme (P13-08, page de maintenance et mobile), lecture seule.
    // `app_version` : version courante et minimale de l'application mobile (P13-02), lecture seule.
    $publicFamilies = '/^api\.(health|status|app_version|referentials|zones|violence_types|needs|auth|thematiques|ressources|urgences|signalements|sos|assistant)\b/';

    foreach (apiRoutes() as $route) {
        $name = (string) $route->getName();
        $middleware = $route->gatherMiddleware();

        expect($name)->not->toBe('', "route sans nom : {$route->uri()}");

        if (! isPublicApiRoute($route)) {
            continue;
        }

        expect(preg_match($publicFamilies, $name))->toBe(1, "{$name} est public mais hors des familles publiques d'ADR-31");

        if (array_diff($route->methods(), ['GET', 'HEAD']) !== []) {
            // `throttle:<nom>`, ou `idempotent:…,limit=<nom>` (limiteur appliqué après la clé : un rejeu ne compte pas).
            $named = array_filter($middleware, fn (string $m): bool => (str_starts_with($m, 'throttle:') && $m !== 'throttle:api')
                || preg_match('/^idempotent:(.+,)?limit=\w+/', $m) === 1);
            expect($named)->not->toBeEmpty("{$name} : écriture publique sans limiteur nommé");
        }
    }
});

it('ne bloque jamais un SOS (limiteur sos hors throttle:api) mais journalise et prévient l’administration au-delà de 5/min', function (): void {
    config(['xomiton.rate_limits.api_per_minute' => 2]);
    expect(config('xomiton.rate_limits.sos_log_per_minute_per_ip'))->toBe(5);
    Log::spy();
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $member = User::factory()->create();

    Route::middleware('api')->prefix('api/v1/_test')->group(function (): void {
        Route::post('sos', static fn () => response()->json(['data' => ['sent' => true]], 201))
            ->withoutMiddleware('throttle:api')
            ->middleware('throttle:sos')
            ->name('api._test.sos');
        Route::post('ordinary', static fn () => response()->json(['data' => []]));
    });

    foreach (range(1, 8) as $ignored) {
        $this->postJson('/api/v1/_test/sos', ['uuid' => (string) Str::uuid()])->assertCreated();
    }

    $fingerprint = KeyedHash::make('127.0.0.1', 16);

    Log::shouldHaveReceived('warning')
        ->with(SosBurstWatch::LOG_MESSAGE, Mockery::on(fn (array $context): bool => $context['route'] === 'api._test.sos'
            && ! in_array('127.0.0.1', $context, true) && $context['ip_hash'] === $fingerprint
            && $context['ip_hash'] !== substr(hash('sha256', '127.0.0.1'), 0, 16)))
        ->times(3);

    // Une seule cloche par adresse et par quart d'heure, aux seuls comptes alerts.manage, sans l'IP.
    Notification::assertSentToTimes($admin, SosBurstDetected::class, 1);
    Notification::assertSentTo($admin, SosBurstDetected::class, fn (SosBurstDetected $n): bool => $n->count === 6
        && $n->fingerprint === $fingerprint && ! str_contains((string) json_encode($n->toArray($admin)), '127.0.0.1'));
    Notification::assertNotSentTo($member, SosBurstDetected::class);

    // Contre-épreuve : une route ordinaire est bien limitée par throttle:api.
    $this->postJson('/api/v1/_test/ordinary')->assertOk();
    $this->postJson('/api/v1/_test/ordinary')->assertOk();
    $this->postJson('/api/v1/_test/ordinary')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
});

it('laisse passer le SOS même si la surveillance échoue', function (): void {
    Log::spy();
    config(['xomiton.rate_limits.sos_log_per_minute_per_ip' => 0]);

    // Permission absente (aucun seeder) : la recherche des destinataires lève, le SOS répond quand même.
    Route::middleware('api')->post('api/v1/_test/sos-fragile', static fn () => response()->json(['data' => ['sent' => true]], 201))
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:sos');

    $this->postJson('/api/v1/_test/sos-fragile')->assertCreated();
    $this->postJson('/api/v1/_test/sos-fragile')->assertCreated();

    Log::shouldHaveReceived('error')->with('SOS : surveillance des envois répétés indisponible.', Mockery::type('array'))->once();
});
