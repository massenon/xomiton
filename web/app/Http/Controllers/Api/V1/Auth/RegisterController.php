<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Auth\RegisterUser;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/register : l'unique envoi de l'inscription en 3 étapes (décision client du 11/11/2026). Compte créé
 * et jeton délivré immédiatement, mais le compte reste `en attente` de la validation de l'administration
 * (`user.pending_validation = true`) : l'e-mail est confirmé par l'équipe, aucun code n'est envoyé.
 * `email_verification_pending` n'est vrai que si un code `signup` a été envoyé (compte actif d'emblée, mode `peers`).
 */
final class RegisterController extends ApiController
{
    public function __construct(private readonly RegisterUser $register) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = ($this->register)($request->toRegisterData());

        $token = $this->issueToken($user, $request);
        Audit::log('auth.token_issued', $user, sprintf('appareil=%s ; origine=inscription', $this->deviceName($request)), $user);

        return $this->data([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user->fresh() ?? $user, $request),
            'email_verification_pending' => $user->status !== UserStatus::Pending,
        ], 201);
    }
}
