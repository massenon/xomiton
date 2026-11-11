<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Auth\AuthenticateUser;
use App\Application\Auth\DeviceTrust;
use App\Application\Auth\ExchangeResetCode;
use App\Application\Auth\Support\MaintenanceMode;
use App\Application\Auth\Support\SignedResetToken;
use App\Application\Auth\VerifyEmail;
use App\Application\Auth\VerifyLoginOtp;
use App\Domain\Auth\Exceptions\AccountSuspended;
use App\Domain\Auth\Exceptions\MaintenanceActive;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Auth\OtpVerifyRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/otp/verify : `login` → jeton + `device_token` (appareil désormais reconnu, Q13 b) ; `signup` →
 * `users.email_verified_at` (accès complet, Q12 c) ; `reset` → `reset_token` signé à présenter à `password/reset`.
 * Une adresse inconnue ou un code faux renvoient le même message (`otp_invalid`, 422) ; l'état du compte (suspendu,
 * maintenance) n'est signalé qu'après un code juste, comme le mot de passe l'exige à la première étape.
 */
final class OtpVerifyController extends ApiController
{
    public function __construct(
        private readonly VerifyLoginOtp $verifyLogin,
        private readonly VerifyEmail $verifyEmail,
        private readonly ExchangeResetCode $exchangeReset,
        private readonly DeviceTrust $devices,
    ) {}

    /**
     * @throws OtpInvalid|AccountSuspended|MaintenanceActive
     */
    public function __invoke(OtpVerifyRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->email())->first();

        if ($user === null) {
            throw new OtpInvalid;
        }

        return match ($request->purpose()) {
            OtpPurpose::Login => $this->login($user, $request),
            OtpPurpose::Signup => $this->signup($user, $request),
            OtpPurpose::Reset => $this->reset($user, $request),
        };
    }

    /**
     * Le code d'abord (sinon un 403 trahirait l'existence d'un compte suspendu sans mot de passe), puis les états
     * qui ont pu changer entre les deux étapes (RG-14 : la maintenance ne laisse entrer qu'admin et superadmin).
     */
    private function login(User $user, OtpVerifyRequest $request): JsonResponse
    {
        ($this->verifyLogin)($user, $request->code());

        if ($user->isSuspended()) {
            Audit::log('auth.refused_suspended', $user, 'etape=otp', null);

            throw new AccountSuspended(Settings::string('contact_email', (string) config('mail.from.address', '')));
        }

        if (! $user->hasAnyRole(AuthenticateUser::ALWAYS_OTP_ROLES) && MaintenanceMode::isActive()) {
            Audit::log('auth.refused_maintenance', $user, 'etape=otp', null);

            throw new MaintenanceActive;
        }

        $token = $this->issueToken($user, $request);
        Audit::log('auth.token_issued', $user, sprintf('appareil=%s ; origine=otp_login', $this->deviceName($request)), $user);

        return $this->data([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user, $request),
            'device_token' => $this->devices->makeCookie($user)->getValue(),
            'device_token_expires_at' => now()->addDays(DeviceTrust::TTL_DAYS)->toIso8601String(),
        ]);
    }

    private function signup(User $user, OtpVerifyRequest $request): JsonResponse
    {
        $viewer = $request->user('sanctum');

        if ($viewer instanceof User && ! $viewer->is($user)) {
            // Un compte connecté ne confirme que sa propre adresse.
            throw new OtpInvalid;
        }

        ($this->verifyEmail)($user, $request->code());

        return $this->data([
            'email_verified' => true,
            'user' => $this->userPayload($user->fresh() ?? $user, $request),
        ]);
    }

    private function reset(User $user, OtpVerifyRequest $request): JsonResponse
    {
        $proof = ($this->exchangeReset)($user, $request->code());

        return $this->data([
            'reset_token' => SignedResetToken::make($proof),
            'expires_at' => $proof->expires_at->toIso8601String(),
        ]);
    }
}
