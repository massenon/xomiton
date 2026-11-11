<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Feed\Concerns;

use App\Models\Post;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Commun aux Form Requests du fil (P7-02) : noms de champs (lang/fr/feed.php `api.attributes`, tu) et publication de la
 * route **visible** par la personne connectée — une publication qu'elle ne peut pas voir est introuvable (404), jamais
 * « interdite » : on ne révèle pas qu'elle existe (même règle que la cloche, docs/API.md §7).
 */
trait FeedRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = [
            'kind' => 'kind', 'body' => 'body', 'tag' => 'tag', 'visibility' => 'visibility', 'is_anonymous' => 'is_anonymous',
            'image' => 'image', 'image_alt' => 'image_alt', 'poll' => 'poll', 'poll.question' => 'poll_question',
            'poll.options' => 'poll_options', 'poll.options.*' => 'poll_option', 'poll.multiple' => 'poll_multiple',
            'poll.results_before_vote' => 'poll_results_before_vote', 'poll.duration_days' => 'poll_duration_days',
            'choices' => 'choices', 'choices.*' => 'choice', 'reason' => 'reason', 'details' => 'details',
            'parent_id' => 'parent_id', 'per_page' => 'per_page', 'group' => 'group',
        ];

        return array_map(static fn (string $key): string => (string) __('feed.api.attributes.'.$key), $fields);
    }

    protected function viewer(): User
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
    public function visiblePost(): Post
    {
        $post = $this->route('post');

        if (! $post instanceof Post || ! $this->viewer()->can('view', $post)) {
            throw new NotFoundHttpException;
        }

        return $post;
    }
}
