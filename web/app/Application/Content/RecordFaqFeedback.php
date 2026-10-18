<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Models\Faq;
use App\Models\FaqFeedback;
use App\Support\KeyedHash;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Vote « Cet article vous a-t-il aidé·e ? » d'une question du centre d'aide (P4-05 ; décision du plan §7 : persister dans
 * `faq_feedback`, compteur « utile » de /admin/contenu en P12-03). Une voix par question et par votant·e : empreinte
 * KeyedHash de la question et d'un jeton aléatoire gardé en session (le jeton CSRF impose une session ouverte par la
 * page), le même que l'on soit connecté·e ou non. Le jeton n'est dérivé ni du compte ni de l'identifiant de session :
 * qui détient APP_KEY ne peut pas retrouver l'auteur·rice d'un vote en essayant les identifiants, et la connexion
 * (session()->regenerate() garde les données) ne permet pas de revoter ; la déconnexion vide la session, donc le jeton.
 * Un nouveau vote du même jeton remplace le précédent ; aucune IP, aucun identifiant n'est enregistré. Les robots et
 * aperçus de liens (App\Support\UserAgent) ne votent pas : la réponse reste la même.
 */
final class RecordFaqFeedback
{
    public const VOTER_KEY = 'faq_feedback_voter';

    public function __invoke(Faq $faq, bool $helpful, Request $request): void
    {
        if (UserAgent::isBot($request->userAgent()) || ! $request->hasSession()) {
            return;
        }

        FaqFeedback::query()->updateOrCreate(
            ['faq_id' => $faq->getKey(), 'fingerprint' => self::fingerprint($faq, $request)],
            ['helpful' => $helpful],
        );
    }

    /** Empreinte de la voix ; crée le jeton de la personne votante s'il manque (la requête doit porter une session). */
    public static function fingerprint(Faq $faq, Request $request): string
    {
        $session = $request->session();
        $voter = $session->get(self::VOTER_KEY);

        if (! is_string($voter) || strlen($voter) !== 40) {
            $voter = Str::random(40);
            $session->put(self::VOTER_KEY, $voter);
        }

        return KeyedHash::make('faq_feedback|'.$faq->getKey().'|'.$voter);
    }
}
