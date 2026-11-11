<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Application\Messaging\ConversationDirectory;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Messaging\ConversationResource;
use App\Http\Resources\Messaging\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Socle des contrôleurs de la messagerie (P8-01, docs/API.md §12) : la personne connectée et la réponse d'une
 * conversation telle qu'elle la voit (ConversationDirectory::view → ConversationResource). Lectures sensibles : jamais
 * gardées par un cache intermédiaire (`no-store` posé par le contrôleur sur les lectures de messages).
 */
abstract class MessagingApiController extends ApiController
{
    public function __construct(protected readonly ConversationDirectory $directory) {}

    protected function viewer(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * Curseur de lecture de l'autre personne (« Vu ») lu par MessageResource : posé sur la requête du conteneur, celle
     * que reçoivent les Resources (la Form Request en est une copie).
     */
    protected function shareReadUpTo(?int $readUpTo): void
    {
        request()->attributes->set(MessageResource::READ_UP_TO, $readUpTo);
    }

    protected function conversationResponse(Request $request, Conversation $conversation, int $status = 200): JsonResponse
    {
        $resource = ConversationResource::make($this->directory->view($this->viewer($request), $conversation->refresh()));

        return $status === 201
            ? $this->created($resource, route('api.conversations.show', ['conversation' => $conversation->ulid]))
            : $this->respond($resource, $status);
    }
}
