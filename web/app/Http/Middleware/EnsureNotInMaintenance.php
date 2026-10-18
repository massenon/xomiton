<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Platform\MaintenanceStatus;
use App\Exceptions\ApiExceptionRenderer;
use App\Models\User;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode maintenance applicatif (RG-14, P2-11, conflit C8 tranché selon MASTER_PROMPT §1 : la sécurité de la personne
 * passe avant tout). Quand la maintenance est active (MaintenanceStatus::active) :
 *  - les parcours garantis « toujours » par RG-01 passent (SOS, signalement, urgences, newsletter et leur API, santé) ;
 *  - la connexion reste ouverte (AuthenticateUser refuse les rôles autres qu'admin / superadmin avec MaintenanceActive) ;
 *  - admin et superadmin travaillent normalement (espace /admin compris) ;
 *  - toute autre requête reçoit une page 503 sobre (gabarit plain, message `maint_msg`, dates), et un·e membre encore
 *    connecté·e est déconnecté·e proprement (session invalidée, journal d'audit).
 */
final class EnsureNotInMaintenance
{
    /** Noms de routes (motifs `Request::routeIs`) toujours servis pendant la maintenance. */
    public const ALLOWED_ROUTES = [
        'sos', 'sos.*', 'signaler', 'signaler.*', 'urgences', 'urgences.*', 'newsletter.*',
        // Accusé de réception d'une alerte par lien signé (P5-02) : l'escalade RG-05 ne s'arrête pas en maintenance.
        'alertes.accuser', 'alertes.accuser.*',
        // Connexion et code de vérification : nécessaires à l'administration ; les autres rôles sont refusés par AuthenticateUser.
        'connexion', 'connexion.*', 'otp', 'otp.*', 'deconnexion',
        'api.health', 'api.auth.login', 'api.auth.otp.*', 'api.auth.logout',
    ];

    /** Chemins (motifs `Request::is`) toujours servis, pour les routes sans nom ou ajoutées plus tard. */
    public const ALLOWED_PATHS = [
        'up', 'sos', 'sos/*', 'signaler', 'signaler/*', 'urgences', 'urgences/*', 'newsletter', 'newsletter/*', 'alertes/*/accuser',
        'connexion', 'code-de-verification', 'code-de-verification/*', 'deconnexion',
        'api/v1/health', 'api/v1/signalements', 'api/v1/signalements/*', 'api/v1/sos', 'api/v1/sos/*',
        'api/v1/urgences', 'api/v1/urgences/*', 'api/v1/newsletter', 'api/v1/newsletter/*',
        'api/v1/auth/login', 'api/v1/auth/otp/*', 'api/v1/auth/logout', 'api/v1/sync/push', 'api/v1/heartbeat',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Liste blanche d'abord (aucune lecture en base) : SOS, signalement et urgences restent servis même si la
        // table des fenêtres ou le cache des paramètres est indisponible, et sans requête supplémentaire.
        if (self::isAlwaysAllowed($request)) {
            return $next($request);
        }

        if (! MaintenanceStatus::active()) {
            return $next($request);
        }

        $user = $this->resolveUser($request);

        if (MaintenanceStatus::isExempt($user)) {
            return $next($request);
        }

        $disconnected = $user !== null && $this->closeSession($request, $user);

        return $this->unavailable($request, $disconnected);
    }

    public static function isAlwaysAllowed(Request $request): bool
    {
        return $request->routeIs(...self::ALLOWED_ROUTES) || $request->is(...self::ALLOWED_PATHS);
    }

    private function resolveUser(Request $request): ?User
    {
        $user = $request->user() ?? ($request->is('api/*') ? $request->user('sanctum') : null);

        return $user instanceof User ? $user : null;
    }

    /**
     * Ferme la session web d'un·e membre connecté·e (jamais un jeton API : il reste valable pour la reprise).
     */
    private function closeSession(Request $request, User $user): bool
    {
        if (! $request->hasSession() || ! Auth::guard('web')->check()) {
            return false;
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Audit::log('maintenance.session_closed', $user, null, $user);

        return true;
    }

    private function unavailable(Request $request, bool $disconnected): Response
    {
        $snapshot = MaintenanceStatus::snapshot();
        $retryAfter = $snapshot->retryAfterSeconds();

        if (ApiExceptionRenderer::wants($request)) {
            return ApiExceptionRenderer::error((string) __('errors.api.maintenance'), 503, 'maintenance', [], ['retry_after' => $retryAfter], ['Retry-After' => (string) $retryAfter]);
        }

        return response()
            ->view('maintenance', $snapshot->toViewData($disconnected), 503)
            ->header('Retry-After', (string) $retryAfter)
            ->header('Cache-Control', 'no-store');
    }
}
