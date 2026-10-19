<?php

declare(strict_types=1);

namespace App\Application\Community\Forums;

use App\Application\Feed\BadWordFilter;
use App\Application\Moderation\ReportContent;
use App\Domain\Community\Exceptions\TopicLocked;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Enums\PostStatus;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Répondre à un sujet (P8-05 ; forum-topic.php l.10-17) : sujet ou forum fermé → 422 `topic_locked` ; filtre de mots du fil
 * (bloquant → 422 neutre, « à surveiller » → signalement automatique) ; réponse anonyme ou non ; `replies_count`,
 * `last_reply_at` du sujet et `posts_count` du forum recalculés sous verrou de la ligne du sujet (deux réponses
 * simultanées ne perdent rien) ; cloche ForumReplyPosted de l'auteur·rice du sujet (ForumReplyNotifier).
 */
final class ReplyToTopic
{
    public function __construct(
        private readonly BadWordFilter $filter,
        private readonly ForumCounters $counters,
        private readonly ReportContent $reports,
        private readonly ForumReplyNotifier $notifier,
    ) {}

    /**
     * @throws TopicLocked
     * @throws ContentBlocked
     */
    public function __invoke(ForumTopic $topic, User $author, string $body, bool $anonymous): ForumReply
    {
        // Forum fermé (`is_active` faux) : aucun sujet n'y reçoit de réponse, même de la modération qui le lit encore
        // (ForumTopicPolicy::reply refuse avant ; ici pour le superadmin, que Gate::before laisse passer).
        if ($topic->isLocked() || $topic->forum?->is_active !== true) {
            throw new TopicLocked;
        }

        $verdict = $this->filter->assertAllowed(['body' => $body]);

        /** @var ForumReply $reply */
        $reply = DB::transaction(function () use ($topic, $author, $body, $anonymous): ForumReply {
            $locked = ForumTopic::query()->whereKey($topic->getKey())->lockForUpdate()->first(['id', 'is_locked']);

            if ($locked === null || $locked->is_locked) {
                throw new TopicLocked;
            }

            $reply = ForumReply::query()->create([
                'topic_id' => $topic->getKey(),
                'user_id' => $author->getKey(),
                'body' => trim($body),
                'is_anonymous' => $anonymous,
                'status' => PostStatus::Published,
            ]);

            $this->counters->afterReplyChange($topic);

            return $reply;
        });

        if ($verdict->isFlagged()) {
            $this->reports->automatic($reply, $verdict);
        }

        $reply->setRelation('topic', $topic)->setRelation('user', $author);
        $this->notifier->replied($topic, $reply, $author);

        return $reply;
    }
}
