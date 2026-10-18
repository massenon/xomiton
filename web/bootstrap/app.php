<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Http\Middleware\HandleConditionalGet;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TagGuestResponse;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Administration : Livewire, préfixe /admin, réservée aux rôles disposant de admin.access.
            Route::middleware(['web', 'auth', 'can:admin.access'])
                ->prefix('admin')
                ->as('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lot securite-http (P2-08, P2-13) : en-têtes de sécurité et nonce CSP sur toutes les réponses (web, API, /up).
        $middleware->append(SecurityHeaders::class);
        // Groupe api : limiteur nommé 'api' (AppServiceProvider::registerRateLimiters, xomiton.rate_limits.api_per_minute).
        // Laravel ne l'ajoute pas tout seul : sans cet appel, /api/v1 n'est pas limité.
        $middleware->throttleApi();
        // Lot api-auth (ADR-31, P2-06) : Sanctum en mode SPA pour l'espace Vue (même domaine, cookie de session + XSRF-TOKEN) ;
        // Flutter garde le jeton Bearer. Sans cet appel, le cookie de session n'authentifie pas /api/v1.
        $middleware->statefulApi();
        // Lot pairs-maintenance (P2-11, RG-14) : mode maintenance applicatif après session et authentification
        // (web et API), liste blanche SOS / signalement / urgences / connexion, admin et superadmin exemptés.
        $middleware->web(append: [EnsureNotInMaintenance::class]);
        // Lot service-worker (P5-10, ADR-26) : `X-Xm-Audience: guest` sur les pages rendues sans compte ni message flash,
        // les seules que le service worker garde à la consultation.
        $middleware->web(append: [TagGuestResponse::class]);
        $middleware->api(append: [EnsureNotInMaintenance::class]);
        // Évalué avant `auth` / `auth:sanctum` : un client dont la session ou le jeton est encore valable reçoit 503
        // (et non 401, que Flutter prendrait pour une déconnexion) ; après StartSession pour pouvoir fermer la session.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureNotInMaintenance::class);

        // Q12 c : l'alias `verified` remplace celui de Laravel (lien signé) par la vérification OTP différée
        // (403 « Confirme ton e-mail » tant que users.email_verified_at est nul).
        // Lot api-fondation (P5-09, ADR-32) : `idempotent` (en-tête facultatif) ou `idempotent:required` sur les POST
        // rejouables par la boîte d'envoi (signalements, sos, sync/push), options `nonblocking` (sos) et `limit=<limiteur>` ;
        // réponses mémorisées dans idempotency_keys.
        // Un seul appel à alias() : chaque appel remplace la liste des alias personnalisés.
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'idempotent' => HandleIdempotencyKey::class,
            // Lot api-me (P6-01) : ETag + If-None-Match → 304 sur les lectures du groupe auth:sanctum de routes/api.php.
            'conditional' => HandleConditionalGet::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('connexion'));
        $middleware->redirectUsersTo(fn () => route('fil'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Lots api-auth (P2-06, P2-12) et api-fondation (P5-09) : /api/v1 répond toujours en JSON { message, code, errors? }
        // (401, 403, 404, 405, 409, 419, 422 validation_failed, 429, 500 hors débogage, exceptions métier DomainException).
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => ApiExceptionRenderer::wants($request));
        $exceptions->render(fn (Throwable $e, Request $request) => ApiExceptionRenderer::render($e, $request));
    })->create();
