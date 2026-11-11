<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Offline\OutboxAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejeu de la boîte d'envoi (P5-10, ADR-26) sur POST /api/v1/sos et /api/v1/signalements : quand l'en-tête
 * X-Xm-Outbox-Account annonce un autre compte que la session (ou `guest`), la requête est traitée sans compte
 * (`$request->user()` nul, donc ni user_id, ni pseudo, ni contacts du compte prévenus). Sans l'en-tête (Flutter, page
 * SOS en direct), rien ne change. Le cookie de session reste envoyé : Sanctum SPA en a besoin pour le jeton XSRF.
 */
final class BindOutboxAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $announced = $request->headers->get(OutboxAccount::HEADER);

        if (OutboxAccount::mismatch($announced, $request->user() ?? $request->user('sanctum'))) {
            $request->setUserResolver(static fn (): null => null);
        }

        return $next($request);
    }
}
