<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Auth\CompleteRegistrationProfile;
use App\Application\Auth\RecordOptionalConsents;
use App\Application\Auth\RegisterData;
use App\Application\Auth\RegisterUser;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/register (Q12 c) : compte créé et jeton délivré immédiatement, code `signup` envoyé, vérification
 * de l'e-mail différée (`email_verification_pending`). `profile` (phases 2-3) et `consents` (phase 4) sont facultatifs.
 */
final class RegisterController extends ApiController
{
    public function __construct(
        private readonly RegisterUser $register,
        private readonly CompleteRegistrationProfile $completeProfile,
        private readonly RecordOptionalConsents $optionalConsents,
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $v */
        $v = $request->validated();

        $user = ($this->register)(new RegisterData(
            pseudo: (string) $v['pseudo'],
            email: (string) $v['email'],
            password: (string) $v['password'],
            acceptsTerms: true,
            acceptsPrivacy: true,
            confirmsAge: (bool) ($v['age_confirmed'] ?? false),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            phone: isset($v['phone']) ? (string) $v['phone'] : null,
        ));

        if (isset($v['profile']) && is_array($v['profile']) && $v['profile'] !== []) {
            ($this->completeProfile)($user, UpdateProfileRequest::profileDataFrom($v['profile']));
        }

        if (isset($v['consents']) && is_array($v['consents'])) {
            ($this->optionalConsents)(
                $user,
                (bool) ($v['consents']['sensitive_data'] ?? false),
                (bool) ($v['consents']['newsletter'] ?? false),
                $request->ip(),
                $request->userAgent(),
            );
        }

        $token = $this->issueToken($user, $request);
        Audit::log('auth.token_issued', $user, sprintf('appareil=%s ; origine=inscription', $this->deviceName($request)), $user);

        return $this->data([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user->fresh() ?? $user, $request),
            'email_verification_pending' => true,
        ], 201);
    }
}
