<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Socle des contrôleurs /api/v1 (SOFTWARE_ARCHITECTURE.md §4, P5-09) : réponses `{ data, meta?, links? }` (meta et links
 * omis quand ils sont vides), erreurs `{ message, code, errors? }` (App\Exceptions\ApiExceptionRenderer). Les contrôleurs
 * restent fins : Form Request → cas d'usage (App\Application) → Resource (App\Http\Resources) → respond()/created().
 */
abstract class ApiController extends Controller
{
    /** Nom de jeton par défaut quand le client n'envoie pas `device_name`. */
    public const DEFAULT_DEVICE_NAME = 'appareil';

    /** Lectures sensibles (genre, orientation, contact de confiance…) : jamais gardées, même par le client (P6-01). */
    protected const NO_STORE = ['Cache-Control' => 'no-store, private'];

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, string|null>  $links
     * @param  array<string, mixed>  $extra  clés de premier niveau ajoutées après data / meta / links
     */
    protected function data(array $data, int $status = 200, array $meta = [], array $links = [], array $extra = []): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        if ($links !== []) {
            $payload['links'] = $links;
        }

        return response()->json($payload + $extra, $status);
    }

    /**
     * Réponse `{ data, meta?, links? }` pour une Resource (ou une collection de Resources) ou un tableau déjà prêt. Comme
     * JsonResource::toResponse(), `with()` et `additional()` de la Resource sont fusionnés (leurs `meta` / `links` sous
     * ceux passés ici, qui l'emportent ; leurs autres clés au premier niveau).
     *
     * @param  JsonResource|array<array-key, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, string|null>  $links
     * @param  array<string, string>  $headers
     */
    protected function respond(JsonResource|array $data, int $status = 200, array $meta = [], array $links = [], array $headers = []): JsonResponse
    {
        if (! $data instanceof JsonResource) {
            return $this->data($data, $status, $meta, $links)->withHeaders($headers);
        }

        $request = request();
        $extra = array_merge_recursive($data->with($request), $data->additional);
        $meta = array_replace((array) ($extra['meta'] ?? []), $meta);
        $links = array_replace((array) ($extra['links'] ?? []), $links);
        unset($extra['data'], $extra['meta'], $extra['links']);

        return $this->data($data->resolve($request), $status, $meta, $links, $extra)->withHeaders($headers);
    }

    /**
     * 201 Created, avec l'en-tête `Location` de la ressource créée quand il est connu.
     *
     * @param  JsonResource|array<array-key, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, string|null>  $links
     */
    protected function created(JsonResource|array $data, ?string $location = null, array $meta = [], array $links = []): JsonResponse
    {
        return $this->respond($data, 201, $meta, $links, $location !== null ? ['Location' => $location] : []);
    }

    /**
     * 202 Accepted : traitement lancé (code envoyé, tâche en file).
     *
     * @param  JsonResource|array<array-key, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    protected function accepted(JsonResource|array $data, array $meta = []): JsonResponse
    {
        return $this->respond($data, 202, $meta);
    }

    protected function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * Liste paginée : `data` (chaque élément passé par la Resource), `meta` (page ou curseur) et `links` (first, last,
     * prev, next). Pagination par page pour l'administration, par curseur pour le fil et les messages (ARCH §4, P6-01).
     * Les liens gardent les filtres de la requête (`withQueryString()`).
     *
     * @param  LengthAwarePaginator<array-key, mixed>|CursorPaginator<array-key, mixed>  $paginator
     * @param  class-string<JsonResource>  $resource
     * @param  array<string, mixed>  $meta
     */
    protected function paginated(LengthAwarePaginator|CursorPaginator $paginator, string $resource, array $meta = []): JsonResponse
    {
        if (method_exists($paginator, 'withQueryString')) {
            $paginator->withQueryString();
        }

        $items = $resource::collection(collect($paginator->items()))->resolve(request());

        if ($paginator instanceof LengthAwarePaginator) {
            return $this->data($items, 200, [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ] + $meta, [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ]);
        }

        return $this->data($items, 200, [
            'per_page' => $paginator->perPage(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
        ] + $meta, [
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ]);
    }

    /**
     * Taille de page demandée (`?per_page=`), bornée à [1, `xomiton.api.max_per_page`] (P6-01) ; valeur absente ou
     * invalide → `$default`. Évite qu'un client demande 10 000 lignes d'un coup.
     */
    protected function perPage(Request $request, int $default = 20): int
    {
        $max = max(1, (int) config('xomiton.api.max_per_page', 50));
        $value = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return min($max, max(1, $value === false ? $default : (int) $value));
    }

    /**
     * Jeton Sanctum nommé d'après l'appareil (P2-06 : « Appareils connectés » du mobile), journalisé par l'appelant.
     */
    protected function issueToken(User $user, Request $request): string
    {
        return $user->createToken($this->deviceName($request))->plainTextToken;
    }

    /**
     * Nom d'appareil saisi, sinon le navigateur (tronqué), sinon un libellé neutre.
     */
    protected function deviceName(Request $request): string
    {
        $name = $request->input('device_name');

        if (is_string($name) && trim($name) !== '') {
            return Str::limit(trim($name), 100, '');
        }

        $agent = trim((string) $request->userAgent());

        return $agent !== '' ? Str::limit($agent, 100, '') : self::DEFAULT_DEVICE_NAME;
    }

    /**
     * Le compte vu par sa propriétaire (e-mail, permissions, réglages compris) : ces réponses ne s'adressent qu'à elle.
     *
     * @return array<string, mixed>
     */
    protected function userPayload(User $user, Request $request): array
    {
        $user->loadMissing(['profile', 'settings', 'zone', 'commune', 'roles']);

        return UserResource::make($user)->asSelf()->resolve($request);
    }
}
