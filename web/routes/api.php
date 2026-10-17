<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpSendController;
use App\Http\Controllers\Api\V1\Auth\OtpVerifyController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

/*
 * API /api/v1 (SOFTWARE_ARCHITECTURE.md §4) : REST JSON, Sanctum, réponses { data, meta, links }, erreurs { message, code, errors }.
 * Tout le groupe porte déjà throttle:api (bootstrap/app.php) ; les routes auth/* ajoutent login (5/min par identifiant + IP)
 * ou otp (5/h par adresse). Documentation : docs/API.md. Les autres familles arrivent phase par phase (P5 alertes, P6 me/,
 * P7 feed, P8 communauté, P9 pair et sync).
 */

Route::get('/health', static fn () => response()->json(['data' => ['status' => 'ok', 'version' => config('xomiton.version', '1.0')]]))
    ->name('api.health');

// --- Authentification (P2-06, P2-12, LOGIN §9) : partagée par Flutter et l'espace Vue ---------------------------------
Route::prefix('auth')->as('api.auth.')->group(function (): void {
    Route::post('register', RegisterController::class)->middleware('throttle:login')->name('register');
    Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');
    Route::post('otp/send', OtpSendController::class)->middleware('throttle:otp')->name('otp.send');
    Route::post('otp/verify', OtpVerifyController::class)->middleware('throttle:login')->name('otp.verify');
    Route::post('password/forgot', ForgotPasswordController::class)->middleware('throttle:otp')->name('password.forgot');
    Route::post('password/reset', ResetPasswordController::class)->middleware('throttle:login')->name('password.reset');
    Route::post('logout', LogoutController::class)->middleware('auth:sanctum')->name('logout');
});

// --- Mon compte ------------------------------------------------------------------------------------------------------
Route::middleware('auth:sanctum')->prefix('me')->as('api.me.')->group(function (): void {
    Route::get('/', [MeController::class, 'show'])->name('show');
    Route::patch('profile', [MeController::class, 'updateProfile'])->name('profile.update');
    Route::get('sessions', [MeController::class, 'sessions'])->name('sessions.index');
    Route::delete('sessions/{id}', [MeController::class, 'destroySession'])->where('id', '[a-z]+-[A-Za-z0-9]+')->name('sessions.destroy');
});
