<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Alerts\EmergencyContactController;
use App\Http\Controllers\Api\V1\Alerts\ReportController;
use App\Http\Controllers\Api\V1\Alerts\SosController;
use App\Http\Controllers\Api\V1\AssistantQuestionController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpSendController;
use App\Http\Controllers\Api\V1\Auth\OtpVerifyController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Me\AccountController;
use App\Http\Controllers\Api\V1\Me\DeviceController;
use App\Http\Controllers\Api\V1\Me\ExportController;
use App\Http\Controllers\Api\V1\Me\NewsletterController as MeNewsletterController;
use App\Http\Controllers\Api\V1\Me\PushSubscriptionController;
use App\Http\Controllers\Api\V1\Me\SecurityController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReferentialController;
use App\Http\Middleware\BindOutboxAccount;
use Illuminate\Support\Facades\Route;

/*
 * API /api/v1 (SOFTWARE_ARCHITECTURE.md §4, docs/API.md) : REST JSON, Sanctum, réponses { data, meta?, links? }
 * (App\Http\Controllers\Api\V1\ApiController), erreurs { message, code, errors? } (App\Exceptions\ApiExceptionRenderer).
 * Tout /api/v1 porte throttle:api (bootstrap/app.php, 60/min par compte ou IP). Deux groupes (ADR-31, P5-09) :
 *   1. public, anonyme : chaque route sensible porte un limiteur nommé (AppServiceProvider::registerRateLimiters) ;
 *   2. authentifié : auth:sanctum (jeton Bearer de Flutter ou cookie de session de Vue en mode SPA).
 * Les POST rejouables par la boîte d'envoi portent `idempotent:required` (ADR-32 : même clé → même réponse), leur limiteur
 * nommé passant par `limit=<nom>` (jamais `throttle:<nom>` à côté : Laravel le trierait avant et un rejeu consommerait le quota).
 * Familles à venir : P7 feed, P8 communauté, P9 pair et sync (groupe 2) ; signalements / sos / urgences livrés (P5-06),
 * me/* et notifications/* livrés (P6-06, P6-07, P6-09).
 */

// === 1. Groupe public, anonyme ===================================================================================
Route::group([], function (): void {
    Route::get('/health', static fn () => response()->json(['data' => ['status' => 'ok', 'version' => config('xomiton.version', '1.0')]]))
        ->name('api.health');

    // --- Référentiels publics (P3-06) : cache serveur 24 h (App\Application\Shared\Referentials), ETag dérivé du contenu,
    // If-None-Match → 304, Cache-Control public 1 h (middleware cache.headers, config xomiton.referentials.http_max_age) -----
    Route::middleware('cache.headers:public;max_age='.(int) config('xomiton.referentials.http_max_age', 3600).';etag')
        ->as('api.referentials.')
        ->group(function (): void {
            Route::get('zones', [ReferentialController::class, 'zones'])->name('zones');
            Route::get('violence-types', [ReferentialController::class, 'violenceTypes'])->name('violence_types');
            Route::get('needs', [ReferentialController::class, 'needs'])->name('needs');
        });

    // --- Authentification (P2-06, P2-12, LOGIN §9) : partagée par Flutter et l'espace Vue ; login 5/min par identifiant
    // + IP, otp 5/h par adresse ---------------------------------------------------------------------------------------
    Route::prefix('auth')->as('api.auth.')->group(function (): void {
        Route::post('register', RegisterController::class)->middleware('throttle:login')->name('register');
        Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');
        Route::post('otp/send', OtpSendController::class)->middleware('throttle:otp')->name('otp.send');
        Route::post('otp/verify', OtpVerifyController::class)->middleware('throttle:login')->name('otp.verify');
        Route::post('password/forgot', ForgotPasswordController::class)->middleware('throttle:otp')->name('password.forgot');
        Route::post('password/reset', ResetPasswordController::class)->middleware('throttle:login')->name('password.reset');
    });

    // --- Assistant public de l'accueil (P4-01, Q41 a) : KnowledgeBaseAnswerer, sans stockage ; 20/min par IP ---------------
    Route::post('assistant/question', AssistantQuestionController::class)->middleware('throttle:assistant')->name('api.assistant.question');

    // --- Parcours de crise (P5-06, docs/API.md §6) : anonymes, liste blanche de maintenance (RG-01) ----------------------
    // signalements : 10/h par IP, rejeux non comptés ; suivi par code 20/h par IP, réponse neutre (`mine` est dans le
    // groupe 2, d'où l'exclusion ci-dessous). sos : jamais de 429 (202 processing si l'envoi d'origine est en cours ; au-delà
    // de 5/min par IP, journal + cloche admin), lecture et actions au compte propriétaire ou au jeton X-Sos-Token (Q52 b).
    Route::prefix('signalements')->as('api.signalements.')->group(function (): void {
        // BindOutboxAccount (P5-10) : rejeu de la boîte d'envoi annonçant un autre compte que la session → sans compte.
        Route::post('/', [ReportController::class, 'store'])->middleware(['idempotent:required,limit=report', BindOutboxAccount::class])->name('store');
        Route::get('{code}', [ReportController::class, 'show'])->where('code', '(?!mine$)[^/]{1,40}')
            ->middleware('throttle:tracking')->name('show');
    });

    Route::prefix('sos')->as('api.sos.')->group(function (): void {
        Route::post('/', [SosController::class, 'store'])
            ->withoutMiddleware('throttle:api')->middleware(['idempotent:required,nonblocking,limit=sos', BindOutboxAccount::class])->name('store');
        // Suivi, repentir et « Je suis en sécurité » hors limiteur global `api` (quota par IP partagé derrière un CGNAT) :
        // limiteurs propres par IP + alerte seulement.
        Route::get('{alert:ulid}', [SosController::class, 'show'])
            ->withoutMiddleware('throttle:api')->middleware('throttle:sos_status')->name('show');
        Route::post('{alert:ulid}/cancel', [SosController::class, 'cancel'])
            ->withoutMiddleware('throttle:api')->middleware('throttle:sos_owner')->name('cancel');
        Route::post('{alert:ulid}/safe', [SosController::class, 'safe'])
            ->withoutMiddleware('throttle:api')->middleware('throttle:sos_owner')->name('safe');
    });

    // urgences : même source que /urgences (ListEmergencyContacts), cache HTTP comme les référentiels.
    Route::get('urgences', EmergencyContactController::class)
        ->middleware('cache.headers:public;max_age='.(int) config('xomiton.referentials.http_max_age', 3600).';etag')
        ->name('api.urgences.index');
});

// === 2. Groupe authentifié (auth:sanctum) =========================================================================
// `conditional` (P6-01) : ETag + If-None-Match → 304 sur toute lecture 200 du groupe (Cache-Control: private, no-cache).
Route::middleware(['auth:sanctum', 'conditional'])->group(function (): void {
    Route::prefix('auth')->as('api.auth.')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('logout');
    });

    // --- Mes signalements (P5-06) : alertes non anonymes du compte, pagination par page --------------------------------
    Route::get('signalements/mine', [ReportController::class, 'mine'])->name('api.signalements.mine');

    // --- Mon compte (P2-06, P6-06, P6-09) : profil, réglages, sécurité, newsletter, export RGPD, suppression, appareils
    // connectés, appareils de l'application (sync_devices) et abonnements push -----------------------------------------
    Route::prefix('me')->as('api.me.')->group(function (): void {
        Route::get('/', [MeController::class, 'show'])->name('show');
        Route::delete('/', [AccountController::class, 'destroy'])->middleware('throttle:account_sensitive')->name('destroy');
        Route::get('profile', [MeController::class, 'profile'])->name('profile.show');
        Route::patch('profile', [MeController::class, 'updateProfile'])->name('profile.update');
        Route::get('settings', [MeController::class, 'settings'])->name('settings.show');
        Route::patch('settings', [MeController::class, 'updateSettings'])->name('settings.update');
        Route::get('security', [SecurityController::class, 'show'])->name('security.show');
        Route::patch('security', [SecurityController::class, 'update'])->name('security.update');
        Route::get('newsletter', [MeNewsletterController::class, 'show'])->name('newsletter.show');
        Route::patch('newsletter', [MeNewsletterController::class, 'update'])->name('newsletter.update');
        Route::post('export', [ExportController::class, 'store'])->middleware('throttle:account_sensitive')->name('export');
        Route::get('sessions', [MeController::class, 'sessions'])->name('sessions.index');
        Route::delete('sessions/{id}', [MeController::class, 'destroySession'])->where('id', '[a-z]+-[A-Za-z0-9]+')->name('sessions.destroy');
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices/{uuid}', [DeviceController::class, 'destroy'])->whereUuid('uuid')->name('devices.destroy');
        Route::get('push-subscriptions', [PushSubscriptionController::class, 'index'])->name('push_subscriptions.index');
        Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push_subscriptions.store');
        Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push_subscriptions.destroy');
    });

    // --- Cloche (P6-07) : liste paginée, compteurs par filtre (polling 30 s, ETag), lecture, suppression -------------
    Route::prefix('notifications')->as('api.notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'counts'])->name('unread_count');
        Route::post('read-all', [NotificationController::class, 'readAll'])->name('read_all');
        Route::post('{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('read');
        Route::delete('{notification}', [NotificationController::class, 'destroy'])->whereUuid('notification')->name('destroy');
    });
});
