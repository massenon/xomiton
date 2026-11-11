<?php

declare(strict_types=1);

namespace App\Application\Moderation;

use App\Application\Community\Forums\ForumCounters;
use App\Application\Feed\FeedCounters;
use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Message;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Masquer ou rendre visible un contenu signalé, avec les compteurs qui suivent (publication, commentaire, sujet, réponse
 * de forum : `status` masque / publie ; message privé : retiré pour tout le monde, `deleted_for_everyone_at`). Partagé par
 * le masquage automatique RG-16 (ReportContent) et par les décisions de la modération (DecideReport, P12-06). Un contenu
 * que l'auteur·rice a supprimé n'est jamais touché. Les messages privés ne sont pas rendus visibles : on ne sait pas
 * distinguer le retrait par la modération de celui de l'auteur·rice (plan Q91).
 */
final class ContentVisibility
{
    public function __construct(
        private readonly FeedCounters $counters,
        private readonly ForumCounters $forumCounters,
    ) {}

    /** Le contenu peut-il être masqué par la modération ? (un compte ou une nature inconnue : non) */
    public static function hideable(?Model $subject): bool
    {
        return $subject instanceof Post || $subject instanceof Comment || $subject instanceof ForumTopic || $subject instanceof ForumReply || $subject instanceof Message;
    }

    /** Vrai si le contenu est actuellement masqué (par la modération ou par le masquage automatique). */
    public static function isHidden(?Model $subject): bool
    {
        return match (true) {
            $subject instanceof Message => $subject->deleted_for_everyone_at !== null,
            $subject instanceof Post, $subject instanceof Comment, $subject instanceof ForumTopic, $subject instanceof ForumReply => $subject->status === PostStatus::Hidden,
            default => false,
        };
    }

    /** Masque le contenu publié ; vrai si quelque chose a changé. */
    public function hide(Model $subject, ?string $reason = null): bool
    {
        if ($subject instanceof Message) {
            if ($subject->deleted_for_everyone_at !== null) {
                return false;
            }

            $subject->forceFill(['deleted_for_everyone_at' => now()])->save();

            return true;
        }

        if (! ($subject instanceof Post || $subject instanceof Comment || $subject instanceof ForumTopic || $subject instanceof ForumReply)
            || $subject->status !== PostStatus::Published) {
            return false;
        }

        $attributes = ['status' => PostStatus::Hidden];

        if ($subject instanceof Post) {
            $attributes['hidden_reason'] = $reason === null ? null : Str::limit($reason, 200, '');
        }

        $subject->forceFill($attributes)->save();
        $this->recount($subject);

        return true;
    }

    /** Rend visible un contenu masqué (jamais supprimé) ; vrai si quelque chose a changé. */
    public function show(Model $subject): bool
    {
        if (! ($subject instanceof Post || $subject instanceof Comment || $subject instanceof ForumTopic || $subject instanceof ForumReply)
            || $subject->status !== PostStatus::Hidden) {
            return false;
        }

        $attributes = ['status' => PostStatus::Published];

        if ($subject instanceof Post) {
            $attributes['hidden_reason'] = null;
        }

        $subject->forceFill($attributes)->save();
        $this->recount($subject);

        return true;
    }

    private function recount(Post|Comment|ForumTopic|ForumReply $subject): void
    {
        if ($subject instanceof Comment) {
            if ($subject->post !== null) {
                $this->counters->recountComments($subject->post);
            }
        } elseif ($subject instanceof ForumTopic) {
            $this->forumCounters->recountForum((int) $subject->forum_id);
        } elseif ($subject instanceof ForumReply && $subject->topic !== null) {
            $this->forumCounters->afterReplyChange($subject->topic);
        }
    }
}
