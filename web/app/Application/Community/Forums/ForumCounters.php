<?php

declare(strict_types=1);

namespace App\Application\Community\Forums;

use App\Enums\PostStatus;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;

/**
 * Compteurs dénormalisés des forums (P8-05, DATABASE_DICTIONARY.md §4) : `forum_topics.replies_count`, `last_reply_at`,
 * `forums.topics_count` (sujets publiés) et `posts_count` (réponses publiées de ces sujets : « N sujets · M messages » de
 * forums.php l.57, même calcul que l'import du prototype). Recalculés depuis les tables (jamais lu-modifié-écrit) dans
 * la transaction de l'écriture ; requêtes sur le Query Builder : un compteur ne touche pas `updated_at`.
 */
final class ForumCounters
{
    public function recountTopic(ForumTopic $topic): void
    {
        $replies = ForumReply::query()->where('topic_id', $topic->getKey())->where('status', PostStatus::Published);
        $count = (clone $replies)->count();
        $last = (clone $replies)->max('created_at');

        ForumTopic::query()->withTrashed()->whereKey($topic->getKey())->toBase()
            ->update(['replies_count' => $count, 'last_reply_at' => $last]);

        $topic->setAttribute('replies_count', $count);
        $topic->setAttribute('last_reply_at', $last);
    }

    public function recountForum(Forum|int|null $forum): void
    {
        $id = $forum instanceof Forum ? (int) $forum->getKey() : $forum;

        if ($id === null) {
            return;
        }

        $topics = ForumTopic::query()->where('forum_id', $id)->where('status', PostStatus::Published);
        $posts = ForumReply::query()->where('status', PostStatus::Published)->whereIn('topic_id', (clone $topics)->select('id'));
        $values = ['topics_count' => (clone $topics)->count(), 'posts_count' => $posts->count()];

        Forum::query()->whereKey($id)->toBase()->update($values);

        if ($forum instanceof Forum) {
            $forum->forceFill($values)->syncOriginalAttributes(array_keys($values));
        }
    }

    /**
     * Sujet et forum d'une réponse ajoutée, masquée ou retirée.
     */
    public function afterReplyChange(ForumTopic $topic): void
    {
        $this->recountTopic($topic);
        $this->recountForum((int) $topic->forum_id);
    }
}
