<?php

declare(strict_types=1);

namespace App\Application\Community\Forums;

use App\Models\ForumTopic;
use App\Models\User;
use App\Support\CountableVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Compteur de vues d'un sujet (`forum_topics.views_count`, P8-05 ; forum-topic.php l.46) : +1 par session et par sujet,
 * pour un `GET` d'un navigateur (CountableVisit : ni `HEAD`, ni préchargement, ni robot). Client sans session (jeton
 * Bearer du mobile) : une vue par compte et par sujet dans la fenêtre `xomiton.forums.view_window_minutes` (cache file
 * ou database, `Cache::add` atomique). L'auteur·rice ne gonfle pas son propre compteur. Requête directe : `updated_at`
 * ne bouge pas. Rend vrai si la vue a été comptée (la réponse affiche alors le compteur à jour).
 */
final class RecordTopicView
{
    private const SESSION_KEY = 'forums.seen';

    public function __invoke(ForumTopic $topic, Request $request, User $viewer): bool
    {
        if (! CountableVisit::counts($request) || $topic->user_id === $viewer->getKey() || ! $this->firstSighting($topic, $request, $viewer)) {
            return false;
        }

        ForumTopic::query()->whereKey($topic->getKey())->toBase()->increment('views_count');
        $topic->setAttribute('views_count', (int) $topic->views_count + 1);
        $topic->syncOriginalAttribute('views_count');

        return true;
    }

    private function firstSighting(ForumTopic $topic, Request $request, User $viewer): bool
    {
        if ($request->hasSession()) {
            $session = $request->session();
            $key = self::SESSION_KEY.'.'.$topic->getKey();

            if ($session->has($key)) {
                return false;
            }

            $session->put($key, true);

            return true;
        }

        $minutes = max(1, (int) config('xomiton.forums.view_window_minutes', 120));

        return Cache::add('forums:view:'.$topic->getKey().':'.$viewer->getKey(), true, now()->addMinutes($minutes));
    }
}
