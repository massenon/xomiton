<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\PostStatus;
use App\Models\Forum;
use App\Models\ForumTopic;

/**
 * forum_topics.json → forum_topics (forum f1..f5 → forum_id, user 0 → anonyme, views → views_count,
 * ts → created_at). Clé naturelle : forum + titre. Les compteurs des forums sont recalculés en fin.
 */
final class ImportForumTopics extends AbstractImporter
{
    public static function collection(): string
    {
        return 'forum_topics';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $forumKey = $this->required($row['forum'] ?? null, 'forum', 10);
        $forum = Forum::query()->forKey($forumKey)->first() ?? $this->reject(sprintf('forum « %s » inconnu', $forumKey));
        $title = $this->required($row['title'] ?? null, 'title', 200);
        $body = $this->required($row['body'] ?? null, 'body');
        $createdAt = $this->ctx->dateTime($row['ts'] ?? null) ?? now();

        $authorPrototypeId = (int) ($row['user'] ?? 0);
        $userId = $authorPrototypeId > 0 ? $this->ctx->userId($authorPrototypeId) : null;

        if ($authorPrototypeId > 0 && $userId === null) {
            $this->reject(sprintf('auteur·rice #%d inconnu·e', $authorPrototypeId));
        }

        $topic = ForumTopic::withTrashed()
            ->where('forum_id', $forum->getKey())
            ->where('title', $title)
            ->first() ?? new ForumTopic;

        $topic->fill([
            'forum_id' => $forum->getKey(),
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'is_anonymous' => $authorPrototypeId === 0 || ! empty($row['anonymous']),
            'views_count' => max(0, (int) ($row['views'] ?? 0)),
            'status' => PostStatus::Published,
        ]);
        $topic->created_at = $createdAt;
        $topic->deleted_at = null;
        $topic->save();

        $this->ctx->remember('topics', $this->prototypeId($row, $index), (int) $topic->getKey());
        $this->track($topic);
    }

    protected function afterAll(): void
    {
        foreach (Forum::query()->get() as $forum) {
            $topics = ForumTopic::query()->where('forum_id', $forum->getKey());

            $forum->forceFill([
                'topics_count' => $topics->count(),
                'posts_count' => (int) $topics->sum('replies_count'),
            ])->save();
        }
    }
}
