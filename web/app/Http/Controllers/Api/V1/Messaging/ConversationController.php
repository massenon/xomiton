<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Application\Messaging\ManageConversation;
use App\Application\Messaging\MessagingContacts;
use App\Application\Messaging\StartConversation;
use App\Domain\Messaging\Exceptions\RecipientUnavailable;
use App\Http\Requests\Api\Messaging\ListConversationsRequest;
use App\Http\Requests\Api\Messaging\SearchRecipientsRequest;
use App\Http\Requests\Api\Messaging\StoreConversationRequest;
use App\Http\Requests\Api\Messaging\UpdateConversationRequest;
use App\Http\Requests\Api\Messaging\ViewConversationRequest;
use App\Http\Resources\Messaging\ConversationResource;
use App\Http\Resources\Messaging\ParticipantPayload;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Conversations (P8-01, docs/API.md §12.1) : `GET conversations` (liste paginée, recherche, non lus ; sans la liste des
 * participant·es, réservée à `show` ; `?since=<empreinte>` → `meta.unchanged` et `data` vide si rien n'a bougé),
 * `POST conversations` (privée : 201 nouvelle, 200 existante ; groupe : 201), `GET conversations/{ulid}`,
 * `PATCH conversations/{ulid}` (titre d'un groupe), `GET conversations/recipients` (« Nouvelle conversation »).
 */
final class ConversationController extends MessagingApiController
{
    public function index(ListConversationsRequest $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $fingerprint = $this->directory->fingerprint($viewer, $request->search(), $request->unreadOnly(), $request->page());
        $meta = [
            'poll_seconds' => (int) config('xomiton.messaging.list_poll_seconds', 15),
            'idle_poll_seconds' => (int) config('xomiton.messaging.list_poll_idle_seconds', 30),
            'fingerprint' => $fingerprint,
        ];

        // Sondage de la liste (D7, réponses légères) : rien n'a changé depuis le passage précédent → ni participant·es
        // ni aperçus déchiffrés, juste l'empreinte (toujours `no-store` : pas de 304 sur un contenu déchiffré).
        if ($request->since() !== null && hash_equals($fingerprint, $request->since())) {
            return $this->data([], 200, $meta + ['unchanged' => true])->withHeaders(self::NO_STORE);
        }

        $paginator = $this->directory->paginate(
            $viewer,
            $request->search(),
            $request->unreadOnly(),
            $this->perPage($request, (int) config('xomiton.messaging.conversations_per_page', 30)),
        );
        ConversationResource::compact();

        return $this->paginated($paginator, ConversationResource::class, $meta + ['unchanged' => false])->withHeaders(self::NO_STORE);
    }

    public function store(StoreConversationRequest $request, StartConversation $start): JsonResponse
    {
        $viewer = $this->viewer($request);

        if ($request->isGroup()) {
            return $this->conversationResponse($request, $start->group($viewer, $request->title(), $request->userIds()), 201);
        }

        $other = User::query()->with('profile')->find($request->recipientId());

        if ($other === null) {
            throw new RecipientUnavailable;
        }

        [$conversation, $created] = $start->private($viewer, $other);

        return $this->conversationResponse($request, $conversation, $created ? 201 : 200);
    }

    public function show(ViewConversationRequest $request, Conversation $conversation): JsonResponse
    {
        return $this->conversationResponse($request, $request->conversation())->withHeaders(self::NO_STORE);
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        return $this->conversationResponse($request, $manage->rename($request->conversation(), $request->title()));
    }

    public function recipients(SearchRecipientsRequest $request, MessagingContacts $contacts): JsonResponse
    {
        $viewer = $this->viewer($request);
        $users = $contacts->search($viewer, $request->term(), (int) config('xomiton.messaging.recipients_limit', 10));

        return $this->data($users->map(fn (User $user): array => ParticipantPayload::for($user))->values()->all())
            ->withHeaders(self::NO_STORE);
    }
}
