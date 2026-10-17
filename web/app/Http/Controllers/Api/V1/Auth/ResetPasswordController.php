<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Auth\ResetPassword;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/password/reset : nouveau mot de passe sur preuve (`reset_token` d'otp/verify, jeton du lien
 * magique ou code) ; toutes les sessions et tous les jetons sont révoqués, l'appareil n'est plus reconnu.
 */
final class ResetPasswordController extends ApiController
{
    public function __construct(private readonly ResetPassword $reset) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        ($this->reset)($request->email(), $request->proof(), $request->password());

        return $this->data([
            'reset' => true,
            'message' => __('auth.api.password_reset_done'),
        ]);
    }
}
