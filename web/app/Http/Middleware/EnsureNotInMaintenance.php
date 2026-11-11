<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Platform\MaintenancePage;
use App\Application\Platform\MaintenanceStatus;
use App\Exceptions\ApiExceptionRenderer;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode maintenance applicatif (RG-14, P2-11, conflit C8 tranché selon MASTER_PROMPT §1 : la sécurité de la personne
 * passe avant tout). Quand la maintenance est active (MaintenanceStatus::active) :
 *  - les parcours garantis « toujours » par RG-01 passent (SOS, signalement, urgences, newsletter et leur API, santé),
 *    avec les référentiels publics en lecture dont dépend le formulaire de signalement (zones, types de violence,
 *    besoins : `category` et `needs` sont obligatoires, mobile P15 et appareil sans copie dans le cache du SW) ;
 *  - la connexion reste ouverte (AuthenticateUser refuse les rôles autres qu'admin / superadmin avec MaintenanceActive),
 *    ainsi que « mot de passe oublié » et la réinitialisation (anti-verrouillage : un admin qui a oublié son mot de
 *    passe peut rentrer ; la réinitialisation ne connecte personne, la connexion reste refusée aux autres rôles) ;
 *  - admin et superadmin travaillent normalement (espace /admin compris) ;
 *  - toute autre requête reçoit une page 503 sobre (gabarit plain, message `maint_msg`, dates), et un·e membre encore
 *    connecté·e est déconnecté·e proprement (session invalidée, journal d'audit « système » sans IP ni navigateur :
 *    action automatique, pas un geste de la personne).
 * P13-07 / P13-08 : page autonome (MaintenancePage, sans manifest Vite), `Retry-After`, `X-Robots-Tag: noindex` et
 * `X-Xm-Maintenance: 1` sur toute réponse 503 de maintenance ; API et visite Inertia de l'espace membre :
 * `{ message, code: maintenance, retry_after, ends_at, overdue, timezone, maintenance_message }` (écran XmMaintenance,
 * mobile P15) ;
 * `GET /api/v1/status` (état public) toujours servi.
 */
final class EnsureNotInMaintenance
{
    /** En-tête posé sur toute réponse 503 de maintenance (reconnu par l'espace membre Vue, P13-08). */
    public const HEADER = 'X-Xm-Maintenance';

    /** Noms de routes (motifs `Request::routeIs`) toujours servis pendant la maintenance. */
    public const ALLOWED_ROUTES = [
        'sos', 'sos.*', 'signaler', 'signaler.*', 'urgences', 'urgences.*', 'newsletter.*',
        // Référentiels publics en lecture (cache 24 h) : construire le formulaire de signalement (P5-04, mobile P15).
        'api.referentials.*',
        // Accusé de réception d'une alerte par lien signé (P5-02) : l'escalade RG-05 ne s'arrête pas en maintenance.
        'alertes.accuser', 'alertes.accuser.*',
        // Connexion et code de vérification : nécessaires à l'administration ; les autres rôles sont refusés par AuthenticateUser.
        'connexion', 'connexion.*', 'otp', 'otp.*', 'deconnexion',
        // Mot de passe oublié (anti-verrouillage, P13-06) : la réinitialisation ne connecte personne.
        'mot-de-passe-oublie', 'mot-de-passe-oublie.*', 'reinitialisation', 'reinitialisation.*',
        'api.health', 'api.status', 'api.auth.login', 'api.auth.otp.*', 'api.auth.logout', 'api.auth.password.*',
    ];

    /** Chemins (motifs `Request::is`) toujours servis, pour les routes sans nom ou ajoutées plus tard. */
    public const ALLOWED_PATHS = [
        'up', 'sos', 'sos/*', 'signaler', 'signaler/*', 'urgences', 'urgences/*', 'newsletter', 'newsletter/*', 'alertes/*/accuser',
        'connexion', 'code-de-verification', 'code-de-verification/*', 'deconnexion',
        'api/v1/health', 'api/v1/status', 'api/v1/signalements', 'api/v1/signalements/*', 'api/v1/sos', 'api/v1/sos/*',
        'api/v1/urgences', 'api/v1/urgences/*', 'api/v1/newsletter', 'api/v1/newsletter/*',
        'api/v1/zones', 'api/v1/violence-types', 'api/v1/needs',
        'mot-de-passe-oublie', 'nouveau-mot-de-passe',
        'api/v1/auth/login', 'api/v1/auth/otp/*', 'api/v1/auth/logout', 'api/v1/auth/password/*', 'api/v1/sync/push', 'api/v1/heartbeat',
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

        // Action du système (la maintenance ferme la session), pas un geste de la personne : ni IP ni navigateur.
        Audit::logWithoutRequest('maintenance.session_closed', $user, null, AuditLog::ACTOR_SYSTEM);

        return true;
    }

    private function unavailable(Request $request, bool $disconnected): Response
    {
        $snapshot = MaintenanceStatus::snapshot();
        $retryAfter = $snapshot->retryAfterSeconds();
        $headers = ['Retry-After' => (string) $retryAfter, 'X-Robots-Tag' => 'noindex, nofollow', self::HEADER => '1'];

        // API (Flutter, Vue) et visite Inertia de l'espace membre : JSON, l'écran XmMaintenance remplace la page.
        if (ApiExceptionRenderer::wants($request) || $request->hasHeader('X-Inertia')) {
            return ApiExceptionRenderer::error((string) __('errors.api.maintenance'), 503, 'maintenance', [], [
                'retry_after' => $retryAfter,
                'ends_at' => $snapshot->endsAt?->toIso8601String(),
                'overdue' => $snapshot->overdue,
                'timezone' => MaintenancePage::timezone(),
                'maintenance_message' => $snapshot->message,
            ], $headers + ['Cache-Control' => 'no-store']);
        }

        return MaintenancePage::response($snapshot, $disconnected)->withHeaders($headers);
    }
}
