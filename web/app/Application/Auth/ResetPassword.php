<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Support\SignedResetToken;
use App\Application\Mail\EmailTemplateService;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Enums\OtpPurpose;
use App\Events\Auth\PasswordResetCompleted;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Nouveau mot de passe (P2-05) après code OTP `reset` ou lien magique : toutes les sessions et tous les jetons
 * Sanctum sont révoqués, les appareils reconnus oubliés (empreinte du mot de passe), puis l'e-mail système
 * `mot-de-passe-modifie` confirme le changement. L'audit passe par l'écouteur de Illuminate\Auth\Events\PasswordReset.
 */
final class ResetPassword
{
    public const CONFIRMATION_TEMPLATE = 'mot-de-passe-modifie';

    public function __construct(private readonly OtpService $otp) {}

    /**
     * @throws OtpInvalid
     */
    public function __invoke(string $email, string $otpCodeOrSignedToken, string $password): void
    {
        $email = OtpService::normalizeEmail($email);
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            // Même message qu'un code faux : rien ne distingue une adresse inconnue.
            throw new OtpInvalid;
        }

        $this->consumeProof($email, trim($otpCodeOrSignedToken));

        DB::transaction(function () use ($user, $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $this->revokeSessions($user);
            $user->tokens()->delete();
        });

        event(new PasswordReset($user));
        PasswordResetCompleted::dispatch($user);

        EmailTemplateService::send(self::CONFIRMATION_TEMPLATE, $user->email, [
            'user_first_name' => $this->firstName($user),
            'user_pseudo' => $user->pseudo,
        ], 'reset');
    }

    /**
     * @throws OtpInvalid
     */
    private function consumeProof(string $email, string $proof): void
    {
        if (SignedResetToken::looksLikeToken($proof)) {
            $record = SignedResetToken::verify($proof, $email);

            if ($record === null) {
                throw new OtpInvalid;
            }

            $record->forceFill(['consumed_at' => now()])->save();

            return;
        }

        if (! $this->otp->verify($email, OtpPurpose::Reset, $proof)) {
            throw new OtpInvalid;
        }
    }

    /** Sessions en base (SESSION_DRIVER=database, D7) : supprimées pour déconnecter tous les navigateurs. */
    private function revokeSessions(User $user): void
    {
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        }
    }

    private function firstName(User $user): string
    {
        $firstName = trim(Str::before(trim((string) $user->name), ' '));

        return $firstName !== '' ? $firstName : (string) $user->pseudo;
    }
}
