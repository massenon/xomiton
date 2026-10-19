<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Application\Assistant\ForgetConversation;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * DELETE /api/v1/assistant/conversations/{id} (P8-11, « Effacer la conversation » d'un échange enregistré) : 204 ; la
 * conversation d'un autre compte répond 404 comme une conversation inexistante.
 */
final class AssistantConversationController extends ApiController
{
    public function destroy(Request $request, int $conversation, ForgetConversation $forget): Response
    {
        /** @var User $user */
        $user = $request->user();

        $forget($user, $conversation);

        return response()->noContent();
    }
}
