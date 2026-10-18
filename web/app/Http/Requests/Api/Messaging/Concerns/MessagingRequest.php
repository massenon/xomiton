<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging\Concerns;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Commun aux Form Requests de la messagerie (P8-01) : noms de champs (lang/fr/community.php `messages.api.attributes`,
 * tu) et conversation de la route **où la personne est présente** — sinon introuvable (404), jamais « interdite » :
 * on ne révèle pas qu'une conversation existe (aucune permission d'administration n'ouvre une conversation privée).
 */
trait MessagingRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = [
            'kind' => 'kind', 'user_id' => 'user_id', 'user_ids' => 'user_ids', 'user_ids.*' => 'user_id', 'title' => 'title',
            'body' => 'body', 'client_uuid' => 'client_uuid', 'attachment' => 'attachment', 'message_id' => 'message_id',
            'duration' => 'duration', 'reason' => 'reason', 'details' => 'details', 'q' => 'q', 'after' => 'after',
            'filter' => 'filter', 'per_page' => 'per_page',
        ];

        return array_map(static fn (string $key): string => (string) __('community.messages.api.attributes.'.$key), $fields);
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
    public function conversation(): Conversation
    {
        $conversation = $this->route('conversation');

        if (! $conversation instanceof Conversation || ! $this->viewer()->can('view', $conversation)) {
            throw new NotFoundHttpException;
        }

        return $conversation;
    }

    /**
     * Message de la route, dans la conversation de la route (404 sinon).
     *
     * @throws NotFoundHttpException
     */
    public function routeMessage(): Message
    {
        $message = $this->route('message');

        if (! $message instanceof Message || (int) $message->conversation_id !== (int) $this->conversation()->getKey()) {
            throw new NotFoundHttpException;
        }

        return $message;
    }
}
