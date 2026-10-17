<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\CompleteRegistrationProfile;
use App\Application\Auth\ConnectedDevices;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Resources\SessionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/me : mon compte (UserResource), mon profil (phases 2-3, profil progressif) et mes appareils connectés
 * (App\Application\Auth\ConnectedDevices). Rien ici ne touche un autre compte.
 */
final class MeController extends ApiController
{
    public function __construct(
        private readonly CompleteRegistrationProfile $completeProfile,
        private readonly ConnectedDevices $devices,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->data($this->userPayload($user, $request));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        ($this->completeProfile)($user, $request->toProfileData());

        return $this->data($this->userPayload($user->fresh() ?? $user, $request));
    }

    public function sessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $current = $user->currentAccessToken();

        $rows = $this->devices->list(
            $user,
            $current instanceof PersonalAccessToken ? (int) $current->getKey() : null,
            $request->hasSession() ? $request->session()->getId() : null,
        );

        return $this->data(SessionResource::collection($rows)->resolve($request), 200, ['total' => $rows->count()]);
    }

    /**
     * Révoque un jeton (`token-<id>`) ou une session web (`web-<empreinte>`) de la personne connectée seulement.
     */
    public function destroySession(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->devices->revoke($user, $id)) {
            throw new NotFoundHttpException;
        }

        return $this->data(['revoked' => true, 'id' => $id]);
    }
}
