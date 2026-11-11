<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Application\Member\MemberShell;
use App\Application\Messaging\ManageConversation;
use App\Application\Messaging\ReportConversation;
use App\Http\Requests\Api\Messaging\MarkReadRequest;
use App\Http\Requests\Api\Messaging\MuteConversationRequest;
use App\Http\Requests\Api\Messaging\ReportConversationRequest;
use App\Http\Requests\Api\Messaging\ViewConversationRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

/**
 * Gestes sur une conversation (P8-01, docs/API.md §12.3) : lu (`POST read`, renvoie les non lus restants de la
 * conversation — non nuls si `message_id` précède le dernier message — et le compteur de l'en-tête), sourdine
 * (`POST` / `DELETE mute`), quitter (`POST leave`), bloquer / débloquer l'autre personne d'une conversation privée
 * (`POST` / `DELETE block`), signaler (`POST report` : 201, 200 si déjà signalé).
 */
final class ConversationActionController extends MessagingApiController
{
    public function read(MarkReadRequest $request, Conversation $conversation, ManageConversation $manage, MemberShell $shell): JsonResponse
    {
        $viewer = $this->viewer($request);
        $cursor = $manage->markRead($viewer, $request->conversation(), $request->messageId());

        return $this->data([
            'last_read_message_id' => $cursor,
            'unread_count' => $this->directory->unreadIn($viewer, $request->conversation()),
            'unread_conversations' => $shell->counters($viewer)['messages'],
        ]);
    }

    public function mute(MuteConversationRequest $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $manage->mute($this->viewer($request), $request->conversation(), $request->minutes());

        return $this->conversationResponse($request, $request->conversation());
    }

    public function unmute(ViewConversationRequest $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $manage->unmute($this->viewer($request), $request->conversation());

        return $this->conversationResponse($request, $request->conversation());
    }

    public function leave(ViewConversationRequest $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $manage->leave($this->viewer($request), $request->conversation());

        return $this->data(['left' => true]);
    }

    public function block(ViewConversationRequest $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $manage->block($this->viewer($request), $request->conversation());

        return $this->conversationResponse($request, $request->conversation());
    }

    public function unblock(ViewConversationRequest $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $manage->unblock($this->viewer($request), $request->conversation());

        return $this->conversationResponse($request, $request->conversation());
    }

    public function report(ReportConversationRequest $request, Conversation $conversation, ReportConversation $reports): JsonResponse
    {
        $outcome = $reports($this->viewer($request), $request->conversation(), $request->reason(), $request->details(), $request->messageId());

        return $this->data(['reported' => true, 'reason' => $request->reason()->value], $outcome->created ? 201 : 200);
    }
}
