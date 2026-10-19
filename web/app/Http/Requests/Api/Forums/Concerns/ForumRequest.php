<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Forums\Concerns;

use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Commun aux Form Requests des forums (P8-05) : noms de champs (lang/fr/community.php `api.forum_attributes`, tu),
 * personne connectée, et sujet ou réponse de la route **visible** par elle (404 sinon, jamais 403 : on ne révèle pas
 * qu'un contenu existe).
 */
trait ForumRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = ['forum', 'title', 'body', 'is_anonymous', 'q', 'reason', 'details', 'per_page'];

        return array_combine($fields, array_map(static fn (string $key): string => (string) __('community.api.forum_attributes.'.$key), $fields));
    }

    public function viewer(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /**
     * @throws NotFoundHttpException
     */
    public function visibleTopic(): ForumTopic
    {
        $topic = $this->route('topic');

        if (! $topic instanceof ForumTopic || ! $this->viewer()->can('view', $topic)) {
            throw new NotFoundHttpException;
        }

        return $topic;
    }

    /**
     * @throws NotFoundHttpException
     */
    public function visibleReply(): ForumReply
    {
        $reply = $this->route('reply');

        if (! $reply instanceof ForumReply || ! $this->viewer()->can('view', $reply)) {
            throw new NotFoundHttpException;
        }

        return $reply;
    }
}
