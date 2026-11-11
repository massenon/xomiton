<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Auth\OtpService;
use App\Application\Auth\RequestPasswordReset;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/password/forgot (Q8 c) : toujours 202 avec le même message, que le compte existe ou non.
 */
final class ForgotPasswordController extends ApiController
{
    public function __construct(private readonly RequestPasswordReset $requestReset) {}

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        ($this->requestReset)($request->email(), $request->ip());

        return $this->data([
            'sent' => true,
            'message' => __('auth.reset_requested'),
            'expires_in' => OtpService::ttlMinutes() * 60,
        ], 202);
    }
}
