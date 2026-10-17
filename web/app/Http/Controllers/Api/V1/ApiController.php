<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Socle des contrôleurs /api/v1 (SOFTWARE_ARCHITECTURE.md §4) : réponses `{ data, meta }`, erreurs `{ message, code, errors }`
 * (voir App\Exceptions\ApiExceptionRenderer). Les contrôleurs restent fins : Form Request → cas d'usage → Resource.
 */
abstract class ApiController extends Controller
{
    /** Nom de jeton par défaut quand le client n'envoie pas `device_name`. */
    public const DEFAULT_DEVICE_NAME = 'appareil';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    protected function data(array $data, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
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
