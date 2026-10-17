<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Auth\AuthenticateUser;
use App\Application\Auth\DeviceTrust;
use App\Application\Auth\LoginData;
use App\Application\Auth\OtpService;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/login (Q13 b) : mot de passe vérifié par le cas d'usage ; si un code OTP est requis (appareil inconnu
 * ou rôle admin / superadmin) → 202 `{ otp_sent, resend_after, email }` puis `otp/verify` ; sinon 200 `{ token, user,
 * device_token }`. `device_token` est l'équivalent API du cookie `remember_device` (30 jours, lié au compte).
 */
final class LoginController extends ApiController
{
    public function __construct(
        private readonly AuthenticateUser $authenticate,
        private readonly DeviceTrust $devices,
        private readonly OtpService $otp,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        /** @var array<string, string|null> $v */
        $v = $request->validated();

        $result = ($this->authenticate)(new LoginData(
            identifier: (string) $v['login'],
            password: (string) $v['password'],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            deviceCookie: isset($v['device_token']) ? (string) $v['device_token'] : null,
        ));

        $user = $result->user;

        if ($result->requiresOtp) {
            $retryAt = $this->otp->canResendAt($user->email, OtpPurpose::Login);

            return $this->data([
                'otp_sent' => $result->otp !== null,
                'purpose' => OtpPurpose::Login->value,
                'email' => $user->email,
                'resend_after' => $retryAt === null ? 0 : max(0, (int) ceil(now()->diffInSeconds($retryAt, false))),
                'expires_in' => OtpService::ttlMinutes() * 60,
            ], 202);
        }

        $token = $this->issueToken($user, $request);
        Audit::log('auth.token_issued', $user, sprintf('appareil=%s ; origine=connexion', $this->deviceName($request)), $user);

        return $this->data([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user, $request),
            'device_token' => $this->devices->makeCookie($user)->getValue(),
            'device_token_expires_at' => now()->addDays(DeviceTrust::TTL_DAYS)->toIso8601String(),
        ]);
    }
}
