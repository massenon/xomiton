<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Newsletter;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ancienne adresse du prototype `newsletter.php?a=confirm|unsub&t=<jeton>[&c=<campagne>]` (P4-07, P4-11 redirections) :
 * 301 vers `/newsletter/confirmer/{jeton}` ou `/newsletter/desinscription/{jeton}`. Les liens de suivi du prototype
 * (`a=open`, `a=click`) étaient signés avec une autre clé : jamais suivis (pas de redirection ouverte), retour à
 * `/newsletter` comme toute autre valeur.
 */
final class LegacyNewsletterRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->query('t');
        $token = is_string($token) && preg_match('/^[a-f0-9]{32}$/i', $token) === 1 ? strtolower($token) : null;
        $campaign = filter_var($request->query('c'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return match (true) {
            $token !== null && $request->query('a') === 'confirm' => redirect()->route('newsletter.confirm', ['token' => $token], 301),
            $token !== null && $request->query('a') === 'unsub' => redirect()->route('newsletter.unsubscribe', ['token' => $token] + ($campaign !== false ? ['c' => $campaign] : []), 301),
            default => redirect()->route('newsletter.show', status: 301),
        };
    }
}
