<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Auth\OtpService;
use App\Application\Auth\RequestPasswordReset;
use App\Domain\Auth\Exceptions\OtpException;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Auth\OtpSendRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/otp/send : renvoi d'un code. La réponse est toujours 202 et identique que le compte existe ou non ;
 * `login` ne renvoie un code que si une connexion (mot de passe vérifié) est en cours, `signup` seulement pour un compte
 * dont l'e-mail n'est pas encore confirmé, `reset` repasse par le mot de passe oublié (code + lien dans un seul e-mail).
 * Seuls les plafonds (30 s entre deux envois, 5 par heure) répondent 429.
 */
final class OtpSendController extends ApiController
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly RequestPasswordReset $requestReset,
    ) {}

    /**
     * @throws OtpException
     */
    public function __invoke(OtpSendRequest $request): JsonResponse
    {
        $purpose = $request->purpose();
        $email = $request->email();
        $user = User::query()->where('email', $email)->first();

        match ($purpose) {
            OtpPurpose::Login => $this->resendLogin($user, $request->ip()),
            OtpPurpose::Signup => $this->resendSignup($user, $request->ip()),
            OtpPurpose::Reset => ($this->requestReset)($email, $request->ip()),
        };

        return $this->data([
            'otp_sent' => true,
            'purpose' => $purpose->value,
            'resend_after' => $this->resendAfter($email, $purpose),
            'expires_in' => OtpService::ttlMinutes() * 60,
        ], 202);
    }

    /**
     * Délai avant un nouvel envoi. Pour `reset`, toujours la valeur nominale : RequestPasswordReset ne lève jamais de
     * plafond, et un délai calculé trahirait qu'un code existe pour cette adresse (énumération).
     */
    private function resendAfter(string $email, OtpPurpose $purpose): int
    {
        if ($purpose === OtpPurpose::Reset) {
            return OtpService::resendAfterSeconds();
        }

        $retryAt = $this->otp->canResendAt($email, $purpose);

        return $retryAt === null ? OtpService::resendAfterSeconds() : max(0, (int) ceil(now()->diffInSeconds($retryAt, false)));
    }

    /** Un code `login` ne s'obtient jamais sans mot de passe : seul un code encore valable peut être renouvelé. */
    private function resendLogin(?User $user, ?string $ip): void
    {
        if ($user === null || $user->isSuspended() || $this->otp->currentCode($user->email, OtpPurpose::Login) === null) {
            return;
        }

        $this->otp->issue($user->email, OtpPurpose::Login, $ip);
    }

    private function resendSignup(?User $user, ?string $ip): void
    {
        if ($user === null || $user->email_verified_at !== null) {
            return;
        }

        $this->otp->issue($user->email, OtpPurpose::Signup, $ip);
    }
}
