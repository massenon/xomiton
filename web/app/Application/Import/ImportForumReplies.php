<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\PostStatus;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;

/**
 * forum_replies.json → forum_replies (topic → topic_id via la correspondance des sujets, user 0 → anonyme,
 * text → body, ts → created_at). Clé naturelle : sujet + auteur·rice + corps. Les compteurs des sujets
 * (replies_count, last_reply_at) et des forums sont recalculés en fin.
 */
final class ImportForumReplies extends AbstractImporter
{
    /** @var array<int, true> sujets touchés */
    private array $topicIds = [];

    public static function collection(): string
    {
        return 'forum_replies';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $topicId = $this->ctx->topicId($row['topic'] ?? null)
            ?? $this->reject(sprintf('sujet #%s inconnu', (string) ($row['topic'] ?? '?')));
        $body = $this->required($row['text'] ?? $row['body'] ?? null, 'text');
        $createdAt = $this->ctx->dateTime($row['ts'] ?? null) ?? now();

        $authorPrototypeId = (int) ($row['user'] ?? 0);
        $userId = $authorPrototypeId > 0 ? $this->ctx->userId($authorPrototypeId) : null;

        if ($authorPrototypeId > 0 && $userId === null) {
            $this->reject(sprintf('auteur·rice #%d inconnu·e', $authorPrototypeId));
        }

        $reply = ForumReply::withTrashed()
            ->where('topic_id', $topicId)
            ->where('user_id', $userId)
            ->where('body', $body)
            ->first() ?? new ForumReply;

        $reply->fill([
            'topic_id' => $topicId,
            'user_id' => $userId,
            'body' => $body,
            'is_anonymous' => $authorPrototypeId === 0 || ! empty($row['anonymous']),
            'status' => PostStatus::Published,
        ]);
        $reply->created_at = $createdAt;
        $reply->deleted_at = null;
        $reply->save();

        $this->topicIds[$topicId] = true;
        $this->track($reply);
    }

    protected function afterAll(): void
    {
        foreach (ForumTopic::query()->whereIn('id', array_keys($this->topicIds))->get() as $topic) {
            $replies = ForumReply::query()->where('topic_id', $topic->getKey())->where('status', PostStatus::Published->value);

            $topic->forceFill([
                'replies_count' => $replies->count(),
                'last_reply_at' => $replies->max('created_at'),
            ])->save();
        }

        foreach (Forum::query()->get() as $forum) {
            $topics = ForumTopic::query()->where('forum_id', $forum->getKey());

            $forum->forceFill([
                'topics_count' => $topics->count(),
                'posts_count' => (int) $topics->sum('replies_count'),
            ])->save();
        }
    }
}
