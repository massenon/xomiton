<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\DeviceRegistry;
use App\Application\Account\PushSubscriptions;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Me\PushSubscriptionRequest;
use App\Http\Resources\Me\PushSubscriptionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * /api/v1/me/push-subscriptions (P6-09, ADR-25) : abonnements Web Push (navigateur) et FCM (Flutter) du compte.
 * GET : liste (sans endpoint ni jeton) + `meta.vapid_public_key` pour `pushManager.subscribe()`. POST : 201 à la
 * création, 200 si l'abonnement existait déjà pour ce compte. DELETE : 204, même si rien ne correspondait (déconnexion,
 * `onTokenRefresh` : idempotent ; l'abonnement d'un autre compte n'est jamais touché). L'abonnement retient le jeton
 * de la requête et l'appareil `X-Device-Id` : leur révocation le supprime (P6-09).
 */
final class PushSubscriptionController extends ApiController
{
    public function __construct(
        private readonly PushSubscriptions $subscriptions,
        private readonly DeviceRegistry $devices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $rows = $user->pushSubscriptions()->orderByDesc('id')->get();
        $vapid = config('xomiton.notifications.push.webpush.vapid.public_key');

        return $this->respond(PushSubscriptionResource::collection($rows), meta: [
            'total' => $rows->count(),
            'vapid_public_key' => is_string($vapid) && $vapid !== '' ? $vapid : null,
        ]);
    }

    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        [$subscription, $created] = $this->subscriptions->save(
            $user,
            (string) $request->kind(),
            $request->target(),
            $request->publicKey(),
            $request->authToken(),
            $request->deviceLabel(),
            $token instanceof PersonalAccessToken ? (int) $token->getKey() : null,
            $this->devices->resolve($user, $request),
        );

        $resource = new PushSubscriptionResource($subscription);

        return $created ? $this->created($resource, route('api.me.push_subscriptions.index')) : $this->respond($resource);
    }

    public function destroy(PushSubscriptionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->subscriptions->remove($user, $request->target(), $request->kind());

        return $this->noContent();
    }
}
