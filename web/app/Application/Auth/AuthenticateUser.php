<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Support\MaintenanceMode;
use App\Domain\Auth\Exceptions\AccountSuspended;
use App\Domain\Auth\Exceptions\AuthFailed;
use App\Domain\Auth\Exceptions\MaintenanceActive;
use App\Domain\Auth\Exceptions\OtpTooSoon;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Events\Auth\UserAuthenticated;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Hash;

/**
 * Connexion (P2-02, LOGIN §3) : identifiant e-mail ou pseudonyme sans tenir compte de la casse, message indifférencié
 * en cas d'échec, états du compte signalés seulement après vérification du mot de passe (suspendu, maintenance RG-14) ;
 * un compte `en_attente` (pair) se connecte avec un bandeau. OTP `login` seulement si l'appareil n'est pas reconnu ou
 * si le rôle est admin / superadmin (Q13 b). L'ouverture de session (Auth::login, jeton Sanctum) revient à l'appelant.
 */
final class AuthenticateUser
{
    /** @var list<string> */
    public const ALWAYS_OTP_ROLES = ['admin', 'superadmin'];

    private static ?string $dummyHash = null;

    public function __construct(
        private readonly OtpService $otp,
        private readonly DeviceTrust $devices,
    ) {}

    /**
     * @throws AuthFailed|AccountSuspended|MaintenanceActive
     */
    public function __invoke(LoginData $data): LoginResult
    {
        $user = $this->checkCredentials($data);

        if ($user->isSuspended()) {
            Audit::log('auth.refused_suspended', $user, null, null);

            throw new AccountSuspended(Settings::string('contact_email', (string) config('mail.from.address', '')));
        }

        $isAdmin = $user->hasAnyRole(self::ALWAYS_OTP_ROLES);

        if (! $isAdmin && MaintenanceMode::isActive()) {
            Audit::log('auth.refused_maintenance', $user, null, null);

            throw new MaintenanceActive;
        }

        $requiresOtp = $isAdmin || ! $this->devices->cookieMatches($user, $data->deviceCookie);
        $otp = $requiresOtp ? $this->issueLoginCode($user, $data->ip) : null;

        if (! $requiresOtp) {
            $user->forceFill(['last_login_at' => now()])->save();
        }

        Audit::log('auth.authenticated', $user, sprintf('otp_requis=%s', $requiresOtp ? 'oui' : 'non'), $user);
        UserAuthenticated::dispatch($user, $requiresOtp);

        return new LoginResult($user, $requiresOtp, $user->status === UserStatus::Pending, $otp);
    }

    /**
     * @throws AuthFailed
     */
    private function checkCredentials(LoginData $data): User
    {
        $user = $this->findUser($data->identifier);

        if ($user !== null && Hash::check($data->password, (string) $user->getAuthPassword())) {
            return $user;
        }

        if ($user === null) {
            // Même coût qu'une vérification réelle : pas d'indice de temps sur l'existence du compte.
            Hash::check($data->password, self::dummyHash());
        }

        event(new Failed('web', $user, ['login' => $data->identifier]));

        throw new AuthFailed;
    }

    private function findUser(string $identifier): ?User
    {
        $needle = mb_strtolower(trim($identifier));

        if ($needle === '') {
            return null;
        }

        // Groupe explicite : le « ou » ne doit jamais contourner le scope SoftDeletes ni un filtre ajouté plus tard.
        return User::query()
            ->where(function ($query) use ($needle): void {
                $query->whereRaw('LOWER(email) = ?', [$needle])
                    ->orWhereRaw('LOWER(pseudo) = ?', [$needle]);
            })
            ->first();
    }

    private function issueLoginCode(User $user, ?string $ip): ?OtpCode
    {
        try {
            return $this->otp->issue($user->email, OtpPurpose::Login, $ip);
        } catch (OtpTooSoon) {
            // Un code encore valable vient d'être envoyé : l'écran OTP l'attend.
            return $this->otp->currentCode($user->email, OtpPurpose::Login);
        }
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(bin2hex(random_bytes(16)));
    }
}
