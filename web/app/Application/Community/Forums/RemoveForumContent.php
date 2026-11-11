<?php

declare(strict_types=1);

namespace App\Application\Community\Forums;

use App\Enums\PostStatus;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Supprimer un sujet ou une réponse (P8-05) : par son auteur·rice, ou par la modération (`posts.moderate`, journalisé
 * `forum.*_deleted_by_moderation` sans le texte). Statut `supprime` + suppression douce (export RGPD et file de
 * modération gardent la trace), compteurs du sujet et du forum recalculés dans la transaction.
 */
final class RemoveForumContent
{
    public function __construct(private readonly ForumCounters $counters) {}

    public function topic(ForumTopic $topic, User $actor): void
    {
        DB::transaction(function () use ($topic): void {
            $topic->forceFill(['status' => PostStatus::Deleted])->save();
            $topic->delete();
            $this->counters->recountForum((int) $topic->forum_id);
        });

        if ($topic->user_id !== $actor->getKey()) {
            Audit::log('forum.topic_deleted_by_moderation', $topic, null, $actor);
        }
    }

    public function reply(ForumReply $reply, User $actor): void
    {
        DB::transaction(function () use ($reply): void {
            $reply->forceFill(['status' => PostStatus::Deleted])->save();
            $reply->delete();

            if ($reply->topic !== null) {
                $this->counters->afterReplyChange($reply->topic);
            }
        });

        if ($reply->user_id !== $actor->getKey()) {
            Audit::log('forum.reply_deleted_by_moderation', $reply, null, $actor);
        }
    }
}
