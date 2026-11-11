<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiExceptionRenderer;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérification d'e-mail différée et non bloquante (Q12 c, IMPLEMENTATION_PLAN §7).
 *
 * Le compte est créé et la session ouverte dès la phase 1 de l'inscription ; tant que users.email_verified_at
 * est nul, l'accès reste limité (lecture, SOS, signalement, profil). Les routes qui publient, commentent,
 * envoient un message ou rejoignent un groupe portent l'alias `verified` : elles répondent 403 « Confirme ton
 * e-mail » (JSON { message, code: email_unverified } pour l'API). Le bandeau de rappel avec « Renvoyer le code »
 * est inclus par le gabarit connecté (auth.partials.verify-banner). Même règle côté autorisation : Gate 'verified'.
 */
final class EnsureEmailIsVerified
{
    public const CODE = 'email_unverified';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasVerifiedEmail()) {
            if (ApiExceptionRenderer::wants($request)) {
                return response()->json(['message' => __('auth.email_not_verified'), 'code' => self::CODE], 403);
            }

            abort(403, __('auth.email_not_verified'));
        }

        return $next($request);
    }
}
