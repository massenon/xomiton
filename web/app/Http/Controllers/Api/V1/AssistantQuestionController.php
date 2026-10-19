<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Assistant\AskAssistant;
use App\Http\Requests\Api\AssistantQuestionRequest;
use App\Http\Resources\AssistantAnswerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/assistant/question (P4-01, Q41 a ; P8-11) : groupe public. Visiteur·se (offcanvas de l'accueil) : base de
 * connaissances seule, rien n'est enregistré. Compte connecté (cookie de session de /assistant ou jeton Bearer) :
 * fournisseur configuré hors crise (AskAssistant), échange enregistré seulement si `persist` est vrai. Garde-fous de
 * crise toujours d'abord. Limiteur `assistant` par IP en plus de `throttle:api`. Réponse propre à la personne : no-store.
 */
final class AssistantQuestionController extends ApiController
{
    public function __invoke(AssistantQuestionRequest $request, AskAssistant $ask): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');
        $user = $user instanceof User ? $user : null;

        $answer = $ask($request->question(), $user, $request->persist(), $user === null ? null : $request->conversationId());

        return $this->respond(AssistantAnswerResource::make($answer)->forMember($user !== null), headers: ['Cache-Control' => 'no-store, private']);
    }
}
