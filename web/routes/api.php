<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AssistantQuestionController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpSendController;
use App\Http\Controllers\Api\V1\Auth\OtpVerifyController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ReferentialController;
use Illuminate\Support\Facades\Route;

/*
 * API /api/v1 (SOFTWARE_ARCHITECTURE.md §4, docs/API.md) : REST JSON, Sanctum, réponses { data, meta?, links? }
 * (App\Http\Controllers\Api\V1\ApiController), erreurs { message, code, errors? } (App\Exceptions\ApiExceptionRenderer).
 * Tout /api/v1 porte throttle:api (bootstrap/app.php, 60/min par compte ou IP). Deux groupes (ADR-31, P5-09) :
 *   1. public, anonyme : chaque route sensible porte un limiteur nommé (AppServiceProvider::registerRateLimiters) ;
 *   2. authentifié : auth:sanctum (jeton Bearer de Flutter ou cookie de session de Vue en mode SPA).
 * Les POST rejouables par la boîte d'envoi portent `idempotent:required` (ADR-32 : même clé → même réponse), leur limiteur
 * nommé passant par `limit=<nom>` (jamais `throttle:<nom>` à côté : Laravel le trierait avant et un rejeu consommerait le quota).
 * Familles à venir : P5-06 signalements / sos / urgences (groupe 1), P6 me/*, P7 feed, P8 communauté, P9 pair et sync (groupe 2).
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

    // --- Parcours de crise (P5-06, à venir) : limiteurs prêts dans AppServiceProvider -----------------------------------
    //   POST signalements           ->middleware('idempotent:required,limit=report')            10/h par IP, rejeux non comptés
    //   GET  signalements/{code}    ->middleware('throttle:tracking')                          20/h par IP, réponse neutre
    //   POST sos                    ->withoutMiddleware('throttle:api')->middleware('idempotent:required,nonblocking,limit=sos')
    //                               (jamais de 429 : 202 processing si l'envoi d'origine est en cours ; au-delà de 5/min
    //                               par IP, journal + cloche admin, rejeux non comptés)
    //   GET  urgences, zones…       lecture publique (cache.headers comme les référentiels)
});

// === 2. Groupe authentifié (auth:sanctum) =========================================================================
Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('auth')->as('api.auth.')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('logout');
    });

    // --- Mon compte --------------------------------------------------------------------------------------------------
    Route::prefix('me')->as('api.me.')->group(function (): void {
        Route::get('/', [MeController::class, 'show'])->name('show');
        Route::patch('profile', [MeController::class, 'updateProfile'])->name('profile.update');
        Route::patch('settings', [MeController::class, 'updateSettings'])->name('settings.update');
        Route::get('sessions', [MeController::class, 'sessions'])->name('sessions.index');
        Route::delete('sessions/{id}', [MeController::class, 'destroySession'])->where('id', '[a-z]+-[A-Za-z0-9]+')->name('sessions.destroy');
    });
});
