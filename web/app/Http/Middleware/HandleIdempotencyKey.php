<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Shared\Idempotency\IdempotencyRejected;
use App\Application\Shared\Idempotency\IdempotencyStore;
use App\Application\Shared\Idempotency\IdempotentRequest;
use App\Models\IdempotencyKey;
use App\Support\KeyedHash;
use App\Support\RouteAudience;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * En-tête `Idempotency-Key` (P5-09, ADR-32, docs/API.md §1.3) — alias `idempotent` (facultatif), `idempotent:required`,
 * options `nonblocking` (POST sos : jamais de 429, P2-08) et `limit=<limiteur>` (limiteur nommé appliqué après la
 * vérification de la clé : un rejeu ne consomme pas le quota), ex. `idempotent:required,limit=report`.
 * Même clé (UUID) + même route + même acteur → la réponse 2xx d'origine est rejouée à l'identique (statut, corps,
 * `Content-Type`, `Location`) avec `Idempotent-Replayed: true` ; corps différent → 422 `idempotency_key_reused` ;
 * requête d'origine encore en cours → 429 `idempotency_in_progress`, ou 202 `{data: {status: processing}}` + Retry-After
 * en mode `nonblocking`. Sans en-tête : comportement normal (ou 422 `idempotency_key_required` en mode `required`).
 * GET / HEAD / OPTIONS ignorés. Acteur : le compte sur une route `auth:*` (à placer après) ; sur une route publique, jamais
 * le compte (RG-01 / RG-02) : HMAC du jeton Bearer, sinon `guest`. Le limiteur nommé passe par `limit=` et non par
 * `throttle:<nom>` sur la route : Laravel trie ThrottleRequests avant tout middleware hors liste de priorité, un
 * `throttle:report` placé après `idempotent` s'exécuterait quand même avant, et un rejeu de la boîte d'envoi (CGNAT,
 * toutes les 5 min) recevrait 429 au lieu de la réponse d'origine. `throttle:api` (groupe) compte toujours tout.
 */
final class HandleIdempotencyKey
{
    public const HEADER = 'Idempotency-Key';

    public const REPLAYED_HEADER = 'Idempotent-Replayed';

    /** Option de route : requête d'origine en cours → 202 `processing` au lieu de 429 (POST sos). */
    public const NONBLOCKING = 'nonblocking';

    /** Option de route : `limit=<limiteur nommé>` (AppServiceProvider::registerRateLimiters). */
    public const LIMIT_PREFIX = 'limit=';

    /** En-têtes de la réponse d'origine restitués au rejeu (les en-têtes de sécurité sont reposés par SecurityHeaders). */
    private const STORED_HEADERS = ['Content-Type', 'Content-Language', 'Location'];

    public function __construct(
        private readonly IdempotencyStore $store,
        private readonly ThrottleRequests $throttle,
    ) {}

    public function handle(Request $request, Closure $next, string $mode = 'optional', string ...$options): Response
    {
        $next = $this->limited($next, $options);

        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $key = trim((string) $request->headers->get(self::HEADER, ''));

        if ($key === '') {
            if ($mode === 'required') {
                throw IdempotencyRejected::required();
            }

            return $next($request);
        }

        if (! Str::isUuid($key)) {
            throw IdempotencyRejected::invalid();
        }

        try {
            $claim = $this->store->claim($this->describe($request, Str::lower($key)));
        } catch (IdempotencyRejected $e) {
            if ($e->isInProgress() && in_array(self::NONBLOCKING, $options, true)) {
                return $this->processing($e->retryAfter());
            }

            throw $e;
        }

        if ($claim->replay) {
            return $this->replay($claim->record);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->store->release($claim->record);

            throw $e;
        }

        if ($this->isReplayable($response)) {
            $this->store->complete($claim->record, $response->getStatusCode(), $this->headersOf($response), (string) $response->getContent());
        } else {
            $this->store->release($claim->record);
        }

        return $response;
    }

    /**
     * Suite de la chaîne, précédée du limiteur nommé `limit=<nom>` s'il est demandé (requêtes neuves seulement).
     *
     * @param  list<string>  $options
     */
    private function limited(Closure $next, array $options): Closure
    {
        foreach ($options as $option) {
            if (str_starts_with($option, self::LIMIT_PREFIX) && strlen($option) > strlen(self::LIMIT_PREFIX)) {
                $limiter = substr($option, strlen(self::LIMIT_PREFIX));

                return fn (Request $request): Response => $this->throttle->handle($request, $next, $limiter);
            }
        }

        return $next;
    }

    private function describe(Request $request, string $key): IdempotentRequest
    {
        $method = Str::upper($request->method());
        $path = '/'.ltrim($request->path(), '/');
        $route = Str::limit('/'.ltrim((string) ($request->route()?->uri() ?? $request->path()), '/'), 255, '');

        return new IdempotentRequest($key, $this->actor($request), $method, $path, $route, $this->fingerprint($request, $method, $path));
    }

    /**
     * Acteur de la portée. Route authentifiée : le compte. Route publique (signalement, SOS) : jamais le compte, même
     * connecté·e (RG-01 / RG-02 : `alerts.user_id` vidé pour un signalement anonyme) ; un jeton Bearer sépare les
     * appareils sans pouvoir être relié au compte (Sanctum ne garde pas le jeton en clair, sa HMAC ne se recalcule pas).
     */
    private function actor(Request $request): string
    {
        if (RouteAudience::requiresAuthentication($request)) {
            $user = $request->user();

            if ($user !== null) {
                return 'user:'.$user->getAuthIdentifier();
            }
        }

        $token = (string) $request->bearerToken();

        return $token !== '' ? 'bearer:'.KeyedHash::make($token) : 'guest';
    }

    /**
     * Empreinte stable du contenu (HMAC à clé d'application) : clés triées, fichiers réduits à leur nom, taille et
     * SHA-256 ; un fichier refusé par PHP (UPLOAD_ERR_INI_SIZE…) se réduit à son nom et à son code d'erreur, la
     * validation répond ensuite 422 (jamais un 500 que la boîte d'envoi rejouerait sans fin).
     */
    private function fingerprint(Request $request, string $method, string $path): string
    {
        $files = $request->allFiles();
        array_walk_recursive($files, static function (mixed &$file): void {
            $file = $file instanceof UploadedFile ? self::fileFingerprint($file) : null;
        });

        $payload = [$method, $path, self::canonical($request->input()), self::canonical($files)];

        return KeyedHash::make(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * @return list<int|string>
     */
    private static function fileFingerprint(UploadedFile $file): array
    {
        $realPath = $file->isValid() ? $file->getRealPath() : false;
        $digest = is_string($realPath) && is_file($realPath) ? hash_file('sha256', $realPath) : false;

        if ($digest === false) {
            return [$file->getClientOriginalName(), 'error', $file->getError()];
        }

        return [$file->getClientOriginalName(), (int) $file->getSize(), $digest];
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::canonical(...), $value);
    }

    private function isReplayable(Response $response): bool
    {
        return $response->isSuccessful()
            && ! $response instanceof StreamedResponse
            && ! $response instanceof BinaryFileResponse
            && $response->getContent() !== false;
    }

    /**
     * @return array<string, string>
     */
    private function headersOf(Response $response): array
    {
        $headers = [];

        foreach (self::STORED_HEADERS as $name) {
            if ($response->headers->has($name)) {
                $headers[$name] = (string) $response->headers->get($name);
            }
        }

        return $headers;
    }

    /** Requête d'origine encore en cours sur une route `nonblocking` : 202, à rejouer après `Retry-After` (P2-08). */
    private function processing(int $retryAfter): Response
    {
        return response()->json(['data' => ['status' => 'processing', 'retry_after' => $retryAfter]], 202, [
            'Retry-After' => (string) $retryAfter,
        ]);
    }

    private function replay(IdempotencyKey $record): Response
    {
        return response((string) $record->response_body, (int) $record->status_code, ($record->response_headers ?? []) + [
            self::REPLAYED_HEADER => 'true',
        ]);
    }
}
