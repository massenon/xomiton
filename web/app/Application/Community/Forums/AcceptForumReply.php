<?php

declare(strict_types=1);

namespace App\Application\Community\Forums;

use App\Domain\Community\Exceptions\ReplyNotAcceptable;
use App\Enums\PostStatus;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Réponse validée d'un sujet de forum (P18-03, Q97) : l'auteur·rice du sujet marque la réponse qui l'a aidé·e ; la
 * modération (`posts.moderate`) peut aussi la valider ou la retirer (ForumTopicPolicy::accept). Une seule réponse
 * validée par sujet : en valider une autre remplace la précédente. La réponse doit appartenir au sujet et être publiée
 * (ni masquée ni supprimée), le sujet aussi ; un sujet fermé reste validable (la validation n'ajoute aucun message).
 * Un geste de la modération sur le sujet d'une autre personne est journalisé (`forum.reply_accepted_by_moderation`,
 * `forum.reply_unaccepted_by_moderation`, sans texte) ; celui de l'auteur·rice ne l'est pas (geste ordinaire du membre).
 */
final class AcceptForumReply
{
    public function accept(ForumTopic $topic, ForumReply $reply, User $by): ForumTopic
    {
        Gate::forUser($by)->authorize('accept', $topic);

        if ((int) $reply->topic_id !== (int) $topic->getKey() || ! $topic->isPublished() || $reply->trashed() || ! $this->published($reply)) {
            throw new ReplyNotAcceptable;
        }

        $topic->forceFill([
            'accepted_reply_id' => (int) $reply->getKey(),
            'accepted_by' => (int) $by->getKey(),
            'accepted_at' => now(),
        ])->save();

        if (! $this->isAuthor($topic, $by)) {
            Audit::log('forum.reply_accepted_by_moderation', $topic, sprintf('sujet=%d ; reponse=%d', $topic->getKey(), $reply->getKey()), $by);
        }

        return $topic;
    }

    public function unaccept(ForumTopic $topic, User $by): ForumTopic
    {
        Gate::forUser($by)->authorize('accept', $topic);

        if ($topic->accepted_reply_id === null) {
            return $topic;
        }

        $previous = (int) $topic->accepted_reply_id;
        $topic->forceFill(['accepted_reply_id' => null, 'accepted_by' => null, 'accepted_at' => null])->save();

        if (! $this->isAuthor($topic, $by)) {
            Audit::log('forum.reply_unaccepted_by_moderation', $topic, sprintf('sujet=%d ; reponse=%d', $topic->getKey(), $previous), $by);
        }

        return $topic;
    }

    /** Réponse supprimée ou masquée : la validation tombe (appelé par RemoveForumContent). */
    public static function forgetReply(ForumReply $reply): void
    {
        ForumTopic::query()->where('accepted_reply_id', $reply->getKey())
            ->update(['accepted_reply_id' => null, 'accepted_by' => null, 'accepted_at' => null]);
    }

    private function published(ForumReply $reply): bool
    {
        return $reply->getRawOriginal('status') === PostStatus::Published->value;
    }

    private function isAuthor(ForumTopic $topic, User $by): bool
    {
        return $topic->user_id !== null && (int) $topic->user_id === (int) $by->getKey();
    }
}
