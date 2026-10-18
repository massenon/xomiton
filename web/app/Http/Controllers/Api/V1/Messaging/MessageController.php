<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Application\Messaging\ManageConversation;
use App\Application\Messaging\SendMessage;
use App\Http\Requests\Api\Messaging\DeleteMessageRequest;
use App\Http\Requests\Api\Messaging\ListMessagesRequest;
use App\Http\Requests\Api\Messaging\StoreMessageRequest;
use App\Http\Resources\Messaging\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

/**
 * Messages (P8-01, docs/API.md §12.2) : `GET conversations/{ulid}/messages` (historique par curseur, ou `?after=` pour
 * le sondage court de la conversation ouverte, avec `meta.deleted_ids` : messages déjà livrés supprimés pour tout le
 * monde depuis `?since=` — `meta.checked_at` du passage précédent), `POST …/messages` (201 créé, 200 rejeu du même `client_uuid`),
 * `DELETE …/messages/{id}` (supprimer pour tout le monde). Lectures `no-store` (corps déchiffré).
 */
final class MessageController extends MessagingApiController
{
    public function index(ListMessagesRequest $request, Conversation $conversation): JsonResponse
    {
        $viewer = $this->viewer($request);
        $conversation = $request->conversation();
        $readUpTo = $this->directory->readByOthersUpTo($viewer, $conversation);
        $this->shareReadUpTo($readUpTo);
        $meta = [
            'read_up_to' => $readUpTo,
            'poll_seconds' => (int) config('xomiton.feed.messages_poll_seconds', 4),
            // Horloge du serveur : `since` du sondage suivant (suppressions pour tout le monde, `deleted_ids`).
            'checked_at' => now()->toIso8601String(),
        ];
        $after = $request->afterId();

        if ($after !== null) {
            $limit = (int) config('xomiton.messaging.poll_max_messages', 100);
            $messages = $this->directory->messagesAfter($viewer, $conversation, $after, $limit + 1);
            $items = MessageResource::collection($messages->take($limit))->resolve($request);

            return $this->data($items, 200, $meta + [
                'after' => $after,
                'last_id' => $items === [] ? $after : (int) end($items)['id'],
                'has_more' => $messages->count() > $limit,
                'deleted_ids' => $this->directory->deletedSince($viewer, $conversation, $after, $request->deletedSince(), $limit),
            ])->withHeaders(self::NO_STORE);
        }

        $paginator = $this->directory->messages($viewer, $conversation, $this->perPage($request, (int) config('xomiton.messaging.per_page', 30)));

        return $this->paginated($paginator, MessageResource::class, $meta)->withHeaders(self::NO_STORE);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation, SendMessage $send): JsonResponse
    {
        $viewer = $this->viewer($request);
        $conversation = $request->conversation();
        $outcome = $send($viewer, $conversation, $request->body(), $request->clientUuid(), $request->attachment());
        $this->shareReadUpTo($this->directory->readByOthersUpTo($viewer, $conversation));
        $resource = MessageResource::make($outcome->message);

        return $outcome->created
            ? $this->created($resource, null, ['duplicate' => false])
            : $this->respond($resource, 200, ['duplicate' => true]);
    }

    public function destroy(DeleteMessageRequest $request, Conversation $conversation, Message $message, ManageConversation $manage): JsonResponse
    {
        return $this->respond(MessageResource::make($manage->deleteForEveryone($request->routeMessage())->load('user.profile')));
    }
}
