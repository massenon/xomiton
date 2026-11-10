<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Repli web des liens universels `/invitation/*`, `/signup/*` et `/alerte/*` (déclarés dans l'AASA et assetlinks.json
 * pour s'ouvrir dans l'application) : sans l'application installée, le navigateur est renvoyé (302) vers /connexion
 * (décision client). Réponse identique que l'invitation ou l'alerte existe ou non : aucune requête en base, aucune
 * validation, aucune redirection vers une URL fournie par l'appelant. Le code d'invitation n'est ni journalisé ni mis
 * dans une URL de redirection : il ne vit que dans le message flash de la session (une requête), pour proposer le lien
 * « Créer un compte avec cette invitation » (inscription web existante, `?invitation=`). Réponses jamais mises en cache.
 */
final class UniversalLinkFallbackController extends Controller
{
    /** Forme des codes d'invitation (alphabet de TeamInvitations) ; autre chose : message seul, sans lien. */
    private const CODE_PATTERN = '/^[A-Za-z0-9_-]{4,64}$/';

    public function invitation(Request $request, string $code): RedirectResponse
    {
        $flash = ['universal_link' => 'invitation'];

        if (preg_match(self::CODE_PATTERN, $code) === 1) {
            $flash['universal_link_code'] = $code;
        }

        return $this->toLogin($flash);
    }

    /**
     * Aucune page web par alerte (le SOS d'un compte vit sur /sos, l'écran admin n'a pas de lien direct) : après la
     * connexion, l'accueil de l'espace du compte (LoginRedirect), jamais une URL tirée de l'identifiant.
     */
    public function alert(Request $request, string $id): RedirectResponse
    {
        return $this->toLogin(['universal_link' => 'alert']);
    }

    /** @param  array<string, string>  $flash */
    private function toLogin(array $flash): RedirectResponse
    {
        return redirect()->route('connexion')
            ->with($flash)
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
