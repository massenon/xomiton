<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Enums\PostStatus;
use App\Models\Poll;
use App\Models\User;
use App\Notifications\PollEnded;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clôture des sondages échus (P7-07, tâche `polls:close`) : `closed_at` = échéance (`ends_at`), une seule fois par
 * sondage (mise à jour conditionnelle `closed_at IS NULL`, sûre si deux exécutions se chevauchent), puis cloche
 * « Sondage terminé » (PollEnded, NOTIFICATIONS §2) à l'auteur·rice active de la publication encore publiée. Par lots
 * (`chunkById`) pour tenir dans la minute du cron cPanel. Le vote est déjà refusé dès l'échéance (Poll::isOpen), même
 * avant le passage de la tâche.
 */
final class ClosePolls
{
    /**
     * @return array{closed: int, notified: int}
     */
    public function __invoke(int $chunk = 100): array
    {
        $closed = 0;
        $notified = 0;

        Poll::query()
            ->whereNull('closed_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with(['post' => static fn ($post) => $post->withTrashed()->with('user')])
            ->chunkById($chunk, function ($polls) use (&$closed, &$notified): void {
                /** @var Poll $poll */
                foreach ($polls as $poll) {
                    $updated = Poll::query()->whereKey($poll->getKey())->whereNull('closed_at')->update(['closed_at' => $poll->ends_at]);

                    if ($updated === 0) {
                        continue;
                    }

                    $closed++;

                    if ($this->notify($poll)) {
                        $notified++;
                    }
                }
            });

        return ['closed' => $closed, 'notified' => $notified];
    }

    private function notify(Poll $poll): bool
    {
        $post = $poll->post;
        $author = $post?->user;

        if ($post === null || $post->trashed() || $post->status !== PostStatus::Published || ! $author instanceof User || ! $author->isActive()) {
            return false;
        }

        try {
            $author->notify(new PollEnded(
                pollId: (int) $poll->getKey(),
                postId: (int) $post->getKey(),
                question: (string) $poll->question,
                voters: (int) $poll->voters_count,
                url: PostLinks::show($post),
            ));
        } catch (Throwable $exception) {
            Log::warning('Sondages : notification « Sondage terminé » non envoyée.', ['poll_id' => $poll->getKey(), 'error' => $exception->getMessage()]);

            return false;
        }

        return true;
    }
}
