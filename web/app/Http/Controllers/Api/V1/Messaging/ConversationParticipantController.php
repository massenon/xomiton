<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Application\Messaging\ManageConversation;
use App\Http\Requests\Api\Messaging\ManageParticipantsRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Participant·es d'un groupe (P8-01, docs/API.md §12.3) : `POST conversations/{ulid}/participants` `{user_ids[]}`,
 * `DELETE conversations/{ulid}/participants/{user}` — gestion du groupe seulement.
 */
final class ConversationParticipantController extends MessagingApiController
{
    public function store(ManageParticipantsRequest $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $manage->addParticipants($this->viewer($request), $request->conversation(), $request->userIds());

        return $this->conversationResponse($request, $request->conversation());
    }

    public function destroy(ManageParticipantsRequest $request, Conversation $conversation, User $user, ManageConversation $manage): JsonResponse
    {
        $manage->removeParticipant($request->conversation(), $request->removed());

        return $this->conversationResponse($request, $request->conversation());
    }
}
