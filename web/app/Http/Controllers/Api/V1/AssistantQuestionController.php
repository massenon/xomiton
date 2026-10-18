<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Http\Requests\Api\AssistantQuestionRequest;
use App\Http\Resources\AssistantAnswerResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/assistant/question (P4-01, Q41 a) : l'offcanvas public de l'accueil interroge la base de connaissances
 * (garde-fous de crise d'abord). Aucun échange n'est enregistré, même pour un·e membre connecté·e : l'assistant membre
 * avec historique (P8-11) étendra ce point d'entrée. Limiteur `assistant` par IP en plus de `throttle:api`.
 */
final class AssistantQuestionController extends ApiController
{
    public function __invoke(AssistantQuestionRequest $request, KnowledgeBaseAnswerer $answerer): JsonResponse
    {
        return $this->respond(AssistantAnswerResource::make($answerer->answer($request->question())));
    }
}
