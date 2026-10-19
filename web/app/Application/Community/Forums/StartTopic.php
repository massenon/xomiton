<?php

declare(strict_types=1);

namespace App\Application\Community\Forums;

use App\Application\Community\Data\TopicDraft;
use App\Application\Feed\BadWordFilter;
use App\Application\Moderation\ReportContent;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Enums\PostStatus;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ouvrir un sujet (P8-05 ; modale « Nouveau sujet » de forums.php l.9-16) : filtre de mots du fil sur le titre et le
 * message (bloquant → 422 neutre, « à surveiller » → signalement automatique, le sujet reste en ligne), sujet publié,
 * anonyme ou non (« Membre anonyme » partout, RG-02 : l'auteur·rice reste en base pour la modération et l'export RGPD,
 * jamais dans l'API), compteurs du forum recalculés dans la transaction.
 */
final class StartTopic
{
    public function __construct(
        private readonly BadWordFilter $filter,
        private readonly ForumCounters $counters,
        private readonly ReportContent $reports,
    ) {}

    /**
     * @throws ContentBlocked
     */
    public function __invoke(User $author, TopicDraft $draft): ForumTopic
    {
        $verdict = $this->filter->assertAllowed(['title' => $draft->title, 'body' => $draft->body]);

        /** @var ForumTopic $topic */
        $topic = DB::transaction(function () use ($author, $draft): ForumTopic {
            $topic = ForumTopic::query()->create([
                'forum_id' => $draft->forum->getKey(),
                'user_id' => $author->getKey(),
                'title' => trim($draft->title),
                'body' => trim($draft->body),
                'is_anonymous' => $draft->anonymous,
                'views_count' => 0,
                'replies_count' => 0,
                'status' => PostStatus::Published,
            ]);

            $this->counters->recountForum($draft->forum);

            return $topic;
        });

        if ($verdict->isFlagged()) {
            $this->reports->automatic($topic, $verdict);
        }

        return $topic->setRelation('forum', $draft->forum)->setRelation('user', $author);
    }
}
