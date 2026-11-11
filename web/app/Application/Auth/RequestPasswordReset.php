<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Support\SignedResetToken;
use App\Application\Mail\EmailTemplateService;
use App\Domain\Auth\Exceptions\OtpException;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mot de passe oublié (P2-05, Q8 c) : réponse identique que le compte existe ou non. Si le compte existe, un seul
 * e-mail `reinitialisation-mot-de-passe` porte le code OTP `reset` et un lien magique signé valable aussi longtemps.
 */
final class RequestPasswordReset
{
    public const TEMPLATE = 'reinitialisation-mot-de-passe';

    public function __construct(private readonly OtpService $otp) {}

    public function __invoke(string $email, ?string $ip = null): void
    {
        $email = OtpService::normalizeEmail($email);
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->simulateWork($email);

            return;
        }

        try {
            $issued = $this->otp->generate($email, OtpPurpose::Reset, $ip);
        } catch (OtpException $e) {
            // Plafond atteint : on ne révèle rien ; le code précédent reste valable.
            Log::info('Réinitialisation : code non renvoyé ('.$e->getMessage().').');

            return;
        }

        $token = SignedResetToken::make($issued->record);

        EmailTemplateService::send(self::TEMPLATE, $email, [
            'otp_code' => $issued->code,
            'otp_expiry' => OtpService::expiryLabel(),
            'reset_link' => SignedResetToken::url($token, $email),
            'user_first_name' => $this->firstName($user),
        ], 'reset');

        Audit::log('auth.password_reset_requested', $user, null, null);
    }

    /** Travail équivalent sans effet pour un compte inconnu : même durée de réponse à quelques millisecondes près. */
    private function simulateWork(string $email): void
    {
        try {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            hash('sha256', $code);

            EmailTemplateService::render(self::TEMPLATE, [
                'otp_code' => $code,
                'otp_expiry' => OtpService::expiryLabel(),
                'reset_link' => SignedResetToken::url(Str::random(60), $email),
                'user_first_name' => '',
            ]);
        } catch (Throwable) {
            // Le rendu à blanc n'a pas d'autre but que d'occuper le même temps.
        }
    }

    private function firstName(User $user): string
    {
        $firstName = trim(Str::before(trim((string) $user->name), ' '));

        return $firstName !== '' ? $firstName : (string) $user->pseudo;
    }
}
