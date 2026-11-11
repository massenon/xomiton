<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiExceptionRenderer;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GARDE CENTRALE des « options limitées » (alias `verified`, web et API) : un compte qui attend la validation de l'équipe,
 * ou dont l'e-mail n'est pas confirmé, peut LIRE, utiliser le SOS, les alertes et le signalement, et modifier son profil,
 * mais ni publier, ni commenter, ni écrire de message, ni rejoindre un groupe ou un événement, ni s'inscrire à une collecte.
 *
 * Depuis l'inscription en 3 étapes (décision client du 11/11/2026), tout compte est `en attente` (UserStatus::Pending) et
 * l'e-mail est confirmé PAR L'ADMINISTRATION à la validation (ReviewRegistration pose `email_verified_at`) : la garde répond
 * alors 403 `account_pending` « Ton compte attend la validation de l'équipe ». Reste le cas du compte actif dont l'e-mail
 * n'est pas confirmé (mode `peers`, vérification par code OTP, Q12 c) : 403 `email_unverified` « Confirme ton e-mail »
 * (JSON { message, code } pour l'API). Les routes qui écrivent portent `verified` ; les cas d'usage qui ne sont limités
 * qu'en partie (participer à un événement, messages hors ligne) appliquent la même règle (User::isPending(), exceptions
 * AccountPending et EmailNotVerified). Les routes de sécurité (SOS, alertes, signalement) ne portent JAMAIS `verified`.
 * Bandeau de rappel : auth.partials.verify-banner (Blade) et XmVerifyBanner (Vue). Même règle côté autorisation : Gate `verified`.
 */
final class EnsureEmailIsVerified
{
    public const CODE = 'email_unverified';

    public const CODE_PENDING = 'account_pending';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->isPending() || ! $user->hasVerifiedEmail())) {
            $pending = $user->isPending();
            $message = (string) __($pending ? 'auth.pending_restricted' : 'auth.email_not_verified');

            if (ApiExceptionRenderer::wants($request)) {
                return response()->json(['message' => $message, 'code' => $pending ? self::CODE_PENDING : self::CODE], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
