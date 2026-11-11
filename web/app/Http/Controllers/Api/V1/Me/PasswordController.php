<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\Settings\ChangePassword;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Me\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * PUT /api/v1/me/password (P8-09) : changement de mot de passe connecté·e (actuel vérifié), autres appareils
 * déconnectés, e-mail `mot-de-passe-modifie` déposé dans la boîte d'envoi. L'accès courant (session de l'espace Vue ou
 * jeton mobile) reste ouvert : la session change d'identifiant et, si l'appareil avait « Se souvenir de moi », son
 * cookie est reposé avec le nouveau `remember_token` (sinon la personne serait déconnectée de l'appareil qu'elle vient de
 * sécuriser à l'expiration de la session).
 */
final class PasswordController extends ApiController
{
    public function update(ChangePasswordRequest $request, ChangePassword $change): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        $revoked = $change(
            $user,
            $request->newPassword(),
            $token instanceof PersonalAccessToken ? (int) $token->getKey() : null,
            $request->hasSession() ? $request->session()->getId() : null,
        );

        if ($request->hasSession()) {
            // Nouvel identifiant de session : l'ancien, s'il a fuité, ne vaut plus rien.
            $request->session()->migrate(true);
            self::keepRememberCookie($request, $user);
        }

        return $this->data(['changed' => true, 'revoked' => $revoked])->withHeaders(self::NO_STORE);
    }

    /**
     * Repose le cookie « se souvenir de moi » de la garde web (même valeur que SessionGuard : id|jeton|HMAC du mot de
     * passe) quand la requête en portait un ; durée `auth.guards.web.remember` ou celle de Laravel (5 ans).
     */
    private static function keepRememberCookie(Request $request, User $user): void
    {
        $guard = Auth::guard('web');

        if (! $guard instanceof SessionGuard || ! $request->cookies->has($guard->getRecallerName())) {
            return;
        }

        $guard->getCookieJar()->queue(
            $guard->getRecallerName(),
            $user->getAuthIdentifier().'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie($user->getAuthPassword()),
            (int) config('auth.guards.web.remember', 576000),
        );
    }
}
