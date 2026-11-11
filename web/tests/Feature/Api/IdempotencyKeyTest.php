<?php

declare(strict_types=1);

use App\Http\Middleware\HandleIdempotencyKey;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Support\KeyedHash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
 * Middleware `idempotent` (P5-09, ADR-32, docs/API.md §1.3) : même clé + même route + même acteur → la réponse 2xx
 * d'origine est rejouée à l'identique sans réexécuter l'action ; corps différent → 422 (jamais 409) ; sans clé →
 * comportement normal ; `idempotent:required` → 422 sans clé ; un échec libère la clé ; en cours → 429, ou 202 en mode
 * `nonblocking` (SOS) ; aucun lien au compte sur une route publique (RG-01 / RG-02) ; stockage chiffré, purge planifiée.
 */

beforeEach(function (): void {
    Cache::flush();
    Sleep::fake();

    $action = static function () {
        $calls = Cache::increment('idempotency-test-calls');
        request()->validate(['amount' => ['required', 'integer', 'min:1']]);

        return response()->json(['data' => ['uuid' => (string) Str::uuid(), 'call' => $calls, 'amount' => (int) request('amount')]], 201)
            ->header('Location', '/api/v1/_test/items/'.$calls)
            ->header('X-Volatile', 'non rejoué');
    };

    Route::middleware('api')->prefix('api/v1/_test')->group(function () use ($action): void {
        Route::post('items', $action)->middleware('idempotent');
        Route::post('other-items', $action)->middleware('idempotent');
        Route::post('required-items', $action)->middleware('idempotent:required');
        Route::get('items', static fn () => response()->json(['data' => ['call' => Cache::increment('idempotency-test-calls')]]))->middleware('idempotent');
        Route::post('crash', static fn () => throw new RuntimeException('boom'))->middleware('idempotent');
        Route::post('secure-items', $action)->middleware(['auth:sanctum', 'idempotent']);
        Route::post('items/{item}/cancel', $action)->middleware('idempotent');
        Route::post('sos-items', $action)->middleware('idempotent:required,nonblocking');
        Route::post('reports', $action)->middleware('idempotent:required,limit=report');
        // Contre-exemple : Laravel trie ThrottleRequests avant `idempotent` quel que soit l'ordre écrit.
        Route::post('reports-naive', $action)->middleware(['idempotent:required', 'throttle:report']);
        Route::post('upload', static function () {
            $calls = Cache::increment('idempotency-test-calls');
            request()->validate(['proof' => ['required', 'file']]);

            return response()->json(['data' => ['call' => $calls]], 201);
        })->middleware('idempotent');
    });
});

function idemCalls(): int
{
    return (int) Cache::get('idempotency-test-calls', 0);
}

it('rejoue la même réponse pour la même clé, la même route et le même acteur sans réexécuter l’action', function (): void {
    $key = (string) Str::uuid();

    $first = $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key]);
    $replay = $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key]);

    $first->assertCreated()->assertHeaderMissing(HandleIdempotencyKey::REPLAYED_HEADER);
    $replay->assertCreated()
        ->assertHeader(HandleIdempotencyKey::REPLAYED_HEADER, 'true')
        ->assertHeader('Location', '/api/v1/_test/items/1')
        ->assertHeaderMissing('X-Volatile');

    expect($replay->getContent())->toBe($first->getContent())
        ->and($replay->headers->get('Content-Type'))->toBe($first->headers->get('Content-Type'))
        ->and(idemCalls())->toBe(1)
        ->and(IdempotencyKey::query()->count())->toBe(1);
});

it('reconnaît la même clé quel que soit l’ordre des champs ou la casse de l’UUID', function (): void {
    $key = (string) Str::uuid();

    $first = $this->postJson('/api/v1/_test/items', ['amount' => 5, 'note' => ['b' => 2, 'a' => 1]], ['Idempotency-Key' => $key]);
    $replay = $this->postJson('/api/v1/_test/items', ['note' => ['a' => 1, 'b' => 2], 'amount' => 5], ['Idempotency-Key' => Str::upper($key)]);

    expect($replay->getContent())->toBe($first->getContent())->and(idemCalls())->toBe(1);
});

it('répond 422 idempotency_key_reused (jamais 409, ADR-32) quand la même clé arrive avec un autre corps', function (): void {
    $key = (string) Str::uuid();
    $message = __('errors.api.idempotency.idempotency_key_reused');

    $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated();
    // Un champ variable ajouté au rejeu (compteur d'essais, horodatage d'envoi) change le corps : refusé.
    $response = $this->postJson('/api/v1/_test/items', ['amount' => 5, 'attempts' => 2], ['Idempotency-Key' => $key]);

    $response->assertUnprocessable()
        ->assertExactJson(['message' => $message, 'code' => 'idempotency_key_reused', 'errors' => ['idempotency_key' => [$message]]]);
    expect(idemCalls())->toBe(1);
});

it('garde le comportement normal sans en-tête Idempotency-Key', function (): void {
    $first = $this->postJson('/api/v1/_test/items', ['amount' => 5])->assertCreated();
    $second = $this->postJson('/api/v1/_test/items', ['amount' => 5])->assertCreated();

    expect($second->json('data.uuid'))->not->toBe($first->json('data.uuid'))
        ->and(idemCalls())->toBe(2)
        ->and(IdempotencyKey::query()->count())->toBe(0);
    $second->assertHeaderMissing(HandleIdempotencyKey::REPLAYED_HEADER);
});

it('exige la clé en mode required (422 idempotency_key_required) et refuse une clé qui n’est pas un UUID', function (): void {
    $this->postJson('/api/v1/_test/required-items', ['amount' => 5])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_required')
        ->assertJsonPath('errors.idempotency_key.0', __('errors.api.idempotency.idempotency_key_required'));

    $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => 'pas-un-uuid'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_invalid');

    expect(idemCalls())->toBe(0);

    $this->postJson('/api/v1/_test/required-items', ['amount' => 5], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
});

it('sépare les portées : une autre route ou un autre acteur avec la même clé exécute l’action', function (): void {
    $key = (string) Str::uuid();
    $awa = User::factory()->create();
    $bea = User::factory()->create();

    $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated();
    $this->postJson('/api/v1/_test/other-items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated()
        ->assertHeaderMissing(HandleIdempotencyKey::REPLAYED_HEADER);

    Sanctum::actingAs($awa);
    $this->postJson('/api/v1/_test/secure-items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated();
    $this->postJson('/api/v1/_test/secure-items', ['amount' => 5], ['Idempotency-Key' => $key])
        ->assertHeader(HandleIdempotencyKey::REPLAYED_HEADER, 'true');

    Sanctum::actingAs($bea);
    $this->postJson('/api/v1/_test/secure-items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated()
        ->assertHeaderMissing(HandleIdempotencyKey::REPLAYED_HEADER);

    expect(idemCalls())->toBe(4)
        ->and(IdempotencyKey::query()->distinct()->count('scope'))->toBe(4);
});

it('ne relie jamais le compte à une requête sur une route publique, même avec un jeton Bearer (RG-01 / RG-02)', function (): void {
    $key = (string) Str::uuid();
    $awa = User::factory()->create();
    $token = $awa->createToken('téléphone')->plainTextToken;
    $other = $awa->createToken('tablette')->plainTextToken;

    $first = $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key, 'Authorization' => 'Bearer '.$token]);
    $this->app['auth']->forgetGuards();
    $replay = $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key, 'Authorization' => 'Bearer '.$token]);
    $this->app['auth']->forgetGuards();
    // Autre jeton (ou sans jeton) : autre portée, l'action est exécutée.
    $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key, 'Authorization' => 'Bearer '.$other])
        ->assertCreated()->assertHeaderMissing(HandleIdempotencyKey::REPLAYED_HEADER);

    $first->assertCreated();
    $replay->assertHeader(HandleIdempotencyKey::REPLAYED_HEADER, 'true');
    expect($replay->getContent())->toBe($first->getContent());

    $row = (array) DB::table('idempotency_keys')->orderBy('id')->first();
    $path = '|POST|/api/v1/_test/items';

    expect(array_keys($row))->not->toContain('actor')->not->toContain('path')
        ->and(json_encode($row))->not->toContain('user:')->not->toContain((string) $awa->id.'|')
        ->and($row['scope'])->not->toBe(KeyedHash::make('user:'.$awa->id.$path))
        ->and($row['scope'])->not->toBe(hash('sha256', 'user:'.$awa->id.$path))
        ->and($row['scope'])->not->toBe(KeyedHash::make('guest'.$path))
        ->and($row['scope'])->toBe(KeyedHash::make('bearer:'.KeyedHash::make($token).$path));
});

it('ne stocke ni le chemin concret (ULID, code de suivi) ni Location en clair', function (): void {
    $ulid = (string) Str::ulid();

    $this->postJson('/api/v1/_test/items/'.$ulid.'/cancel', ['amount' => 5], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertHeader('Location', '/api/v1/_test/items/1');

    $raw = DB::table('idempotency_keys')->first();
    $record = IdempotencyKey::query()->firstOrFail();

    expect($raw->route)->toBe('/api/v1/_test/items/{item}/cancel')
        ->and(json_encode($raw))->not->toContain($ulid)
        ->and((string) $raw->response_headers)->not->toContain('/api/v1/_test/items/1')
        ->and($record->response_headers)->toMatchArray(['Location' => '/api/v1/_test/items/1']);
});

it('libère la clé après un échec : le client corrige et renvoie avec la même clé', function (): void {
    $key = (string) Str::uuid();

    $this->postJson('/api/v1/_test/items', ['amount' => 0], ['Idempotency-Key' => $key])->assertUnprocessable();
    expect(IdempotencyKey::query()->count())->toBe(0);

    $this->postJson('/api/v1/_test/items', ['amount' => 3], ['Idempotency-Key' => $key])->assertCreated()
        ->assertHeaderMissing(HandleIdempotencyKey::REPLAYED_HEADER);

    config(['app.debug' => false]);
    $this->postJson('/api/v1/_test/crash', [], ['Idempotency-Key' => $key])->assertStatus(500);
    expect(IdempotencyKey::query()->where('route', '/api/v1/_test/crash')->exists())->toBeFalse();
});

it('ignore les requêtes GET', function (): void {
    $key = (string) Str::uuid();

    $this->getJson('/api/v1/_test/items', ['Idempotency-Key' => $key])->assertOk();
    $this->getJson('/api/v1/_test/items', ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('data.call', 2);

    expect(IdempotencyKey::query()->count())->toBe(0);
});

it('répond 429 idempotency_in_progress (jamais 409) quand la requête d’origine est encore en cours', function (): void {
    config(['xomiton.idempotency.wait_seconds' => 0]);
    $key = (string) Str::uuid();

    $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated();
    IdempotencyKey::query()->update(['status_code' => null, 'response_body' => null]);

    $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key])
        ->assertStatus(429)
        ->assertHeader('Retry-After', '1')
        ->assertJsonPath('code', 'idempotency_in_progress')
        ->assertJsonPath('retry_after', 1);

    expect(idemCalls())->toBe(1);
});

it('répond 202 processing, jamais 429, sur une route nonblocking (POST sos, P2-08)', function (): void {
    config(['xomiton.idempotency.wait_seconds' => 0]);
    $key = (string) Str::uuid();

    $this->postJson('/api/v1/_test/sos-items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated();
    IdempotencyKey::query()->update(['status_code' => null, 'response_body' => null]);

    $this->postJson('/api/v1/_test/sos-items', ['amount' => 5], ['Idempotency-Key' => $key])
        ->assertStatus(202)
        ->assertHeader('Retry-After', '1')
        ->assertExactJson(['data' => ['status' => 'processing', 'retry_after' => 1]]);

    expect(idemCalls())->toBe(1);
});

it('ne compte pas un rejeu dans le quota du limiteur nommé passé par limit= (jamais 429 au lieu de la réponse d’origine)', function (): void {
    config(['xomiton.rate_limits.report_per_hour_per_ip' => 1]);
    $key = (string) Str::uuid();

    $first = $this->postJson('/api/v1/_test/reports', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated();
    $this->postJson('/api/v1/_test/reports', ['amount' => 5], ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertHeader(HandleIdempotencyKey::REPLAYED_HEADER, 'true');
    expect($first->getContent())->not->toBe('');

    $this->postJson('/api/v1/_test/reports', ['amount' => 5], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests');

    expect(idemCalls())->toBe(1)->and(IdempotencyKey::query()->count())->toBe(1);

    // Sans clé (mode facultatif) ou en `throttle:` écrit sur la route, le quota s'applique avant tout rejeu.
    $naiveKey = (string) Str::uuid();
    config(['xomiton.rate_limits.report_per_hour_per_ip' => 2]);
    Cache::flush();
    $this->postJson('/api/v1/_test/reports-naive', ['amount' => 5], ['Idempotency-Key' => $naiveKey])->assertCreated();
    $this->postJson('/api/v1/_test/reports-naive', ['amount' => 5], ['Idempotency-Key' => $naiveKey])->assertCreated();
    $this->postJson('/api/v1/_test/reports-naive', ['amount' => 5], ['Idempotency-Key' => $naiveKey])->assertStatus(429);
});

it('prend l’empreinte d’un envoi multipart, y compris d’un fichier refusé par PHP (422, jamais 500)', function (): void {
    config(['app.debug' => false]);
    $key = (string) Str::uuid();
    $proof = UploadedFile::fake()->createWithContent('preuve.txt', 'contenu A');

    $this->post('/api/v1/_test/upload', ['proof' => $proof], ['Idempotency-Key' => $key, 'Accept' => 'application/json'])->assertCreated();
    $this->post('/api/v1/_test/upload', ['proof' => $proof], ['Idempotency-Key' => $key, 'Accept' => 'application/json'])
        ->assertCreated()
        ->assertHeader(HandleIdempotencyKey::REPLAYED_HEADER, 'true');
    $this->post('/api/v1/_test/upload', ['proof' => UploadedFile::fake()->createWithContent('preuve.txt', 'contenu B')], ['Idempotency-Key' => $key, 'Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_reused');

    // Fichier au-delà d'upload_max_filesize : PHP ne fournit aucun fichier temporaire (chemin vide).
    $tooBig = new UploadedFile('', 'video.mp4', 'video/mp4', UPLOAD_ERR_INI_SIZE, true);

    $this->post('/api/v1/_test/upload', ['proof' => $tooBig], ['Idempotency-Key' => (string) Str::uuid(), 'Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['proof']);

    expect(idemCalls())->toBe(2)->and(IdempotencyKey::query()->count())->toBe(1);
});

it('attend la fin de la requête d’origine puis rejoue sa réponse', function (): void {
    config(['xomiton.idempotency.wait_seconds' => 2]);
    $key = (string) Str::uuid();

    $first = $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated();
    $record = IdempotencyKey::query()->firstOrFail();
    $stored = ['status_code' => $record->status_code, 'body' => $record->response_body];
    $record->forceFill(['status_code' => null, 'response_body' => null])->save();

    // La requête d'origine se termine pendant la première pause de la requête concurrente.
    Sleep::whenFakingSleep(function () use ($record, $stored): void {
        $record->forceFill(['status_code' => $stored['status_code'], 'response_body' => $stored['body']])->save();
    });

    $replay = $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key]);

    $replay->assertCreated()->assertHeader(HandleIdempotencyKey::REPLAYED_HEADER, 'true');
    expect($replay->getContent())->toBe($first->getContent())->and(idemCalls())->toBe(1);
});

it('reprend une réservation abandonnée et ignore une ligne expirée', function (): void {
    config(['xomiton.idempotency.lock_seconds' => 60, 'xomiton.idempotency.wait_seconds' => 0]);
    $key = (string) Str::uuid();

    $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated();
    DB::table('idempotency_keys')->update(['status_code' => null, 'response_body' => null, 'updated_at' => now()->subMinutes(5)]);

    $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated()
        ->assertJsonPath('data.call', 2);

    DB::table('idempotency_keys')->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/api/v1/_test/items', ['amount' => 9], ['Idempotency-Key' => $key])->assertCreated()
        ->assertJsonPath('data.call', 3);

    expect(IdempotencyKey::query()->count())->toBe(1);
});

it('chiffre le corps mémorisé et fixe l’expiration selon le TTL configuré', function (): void {
    config(['xomiton.idempotency.ttl_hours' => 12]);
    $key = (string) Str::uuid();

    $response = $this->postJson('/api/v1/_test/items', ['amount' => 5], ['Idempotency-Key' => $key])->assertCreated();
    $raw = DB::table('idempotency_keys')->first();
    $record = IdempotencyKey::query()->firstOrFail();

    expect($raw->response_body)->not->toContain((string) $response->json('data.uuid'))
        ->and($record->response_body)->toBe($response->getContent())
        ->and($record->idempotency_key)->toBe($key)
        ->and($record->method)->toBe('POST')
        ->and($record->route)->toBe('/api/v1/_test/items')
        ->and($record->status_code)->toBe(201)
        ->and($record->expires_at->diffInHours(now()->addHours(12), true))->toBeLessThan(1);
});

it('purge les clés expirées seulement (idempotency:purge)', function (): void {
    foreach ([['amount' => 1], ['amount' => 2]] as $body) {
        $this->postJson('/api/v1/_test/items', $body, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    }

    IdempotencyKey::query()->orderBy('id')->first()?->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('idempotency:purge')
        ->expectsOutputToContain('1 clé d’idempotence purgée')
        ->assertSuccessful();

    expect(IdempotencyKey::query()->count())->toBe(1);
});
