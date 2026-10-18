<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /deconnexion : ferme la session courante (l'appareil reste reconnu, l'audit passe par l'écouteur Logout).
 * Espace Vue (P6-02) : Inertia::clearHistory() efface la clé de l'historique chiffré à la visite Inertia suivante ;
 * appelée depuis Inertia (en-tête X-Inertia, resources/js/app/logout.ts), la réponse est un 409 X-Inertia-Location qui
 * recharge entièrement l'accueil (page Blade publique), sinon une redirection classique.
 */
final class LogoutController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return Inertia::location(
            redirect()->route('home')->with('toast', __('auth.logout.done'))->with('toast_type', 'success'),
        );
    }
}
