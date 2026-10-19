<?php

declare(strict_types=1);

namespace App\Application\Account\Settings;

use App\Application\Auth\ConnectedDevices;
use App\Application\Auth\ResetPassword;
use App\Application\Mail\EmailTemplateService;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Changement de mot de passe depuis /parametres (P8-09, prototype settings.php « Mot de passe ») : le mot de passe actuel
 * est vérifié par la requête (`current_password`), le nouveau suit App\Domain\Auth\PasswordStrength (StrongPassword).
 * Les autres appareils sont **toujours** déconnectés (jetons Sanctum et sessions web, sauf l'accès courant) : un
 * changement de mot de passe est le geste d'une personne qui craint pour son compte, et l'e-mail système
 * `mot-de-passe-modifie` (le même que la réinitialisation, P2-05) annonce la fermeture des sessions. L'e-mail est déposé
 * dans la boîte d'envoi (`mail_outbox`, EmailTemplateService::enqueue) : aucune connexion SMTP pendant la requête.
 * Les appareils reconnus (DeviceTrust) tombent d'eux-mêmes : leur empreinte dépend du mot de passe.
 */
final class ChangePassword
{
    public function __construct(private readonly ConnectedDevices $devices) {}

    /**
     * @return int nombre d'accès fermés sur les autres appareils
     */
    public function __invoke(User $user, string $password, ?int $currentTokenId, ?string $currentSessionId): int
    {
        $revoked = DB::transaction(function () use ($user, $password, $currentTokenId, $currentSessionId): int {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            return $this->devices->revokeOthers($user, $currentTokenId, $currentSessionId);
        });

        Audit::log('auth.password_changed', $user, 'acces_fermes='.$revoked, $user);

        EmailTemplateService::enqueue(ResetPassword::CONFIRMATION_TEMPLATE, (string) $user->email, [
            'user_first_name' => self::firstName($user),
            'user_pseudo' => $user->pseudo,
        ], 'settings');

        return $revoked;
    }

    private static function firstName(User $user): string
    {
        $firstName = trim(Str::before(trim((string) $user->name), ' '));

        return $firstName !== '' ? $firstName : (string) $user->pseudo;
    }
}
