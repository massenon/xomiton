<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * POST /api/v1/auth/logout : révoque le jeton Sanctum de la requête (ou ferme la session web d'un client Vue en mode
 * SPA, ADR-31). L'audit passe par l'écouteur de Illuminate\Auth\Events\Logout (App\Listeners\Auth\LogLogout) : le garde
 * `web` émet l'événement lui-même, le jeton le fait ici.
 */
final class LogoutController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
            event(new Logout('sanctum', $user));
        } elseif ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->data(['logged_out' => true]);
    }
}
