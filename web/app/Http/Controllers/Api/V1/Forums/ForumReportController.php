<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Forums;

use App\Application\Moderation\ReportContent;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Forums\ReportForumContentRequest;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use Illuminate\Http\JsonResponse;

/**
 * Signalement d'un sujet ou d'une réponse de forum (P8-05, comme le fil : FR-SOC-06, RG-16, App\Application\Moderation\
 * ReportContent → `moderation_reports` + ContentReported, cloche de la modération) : 201 au premier signalement, 200 si
 * la personne l'avait déjà signalé. La réponse ne dit jamais si le contenu a été masqué ni combien de personnes l'ont
 * signalé.
 */
final class ForumReportController extends ApiController
{
    public function topic(ReportForumContentRequest $request, ForumTopic $topic, ReportContent $reports): JsonResponse
    {
        return $this->report($request, $reports);
    }

    public function reply(ReportForumContentRequest $request, ForumReply $reply, ReportContent $reports): JsonResponse
    {
        return $this->report($request, $reports);
    }

    private function report(ReportForumContentRequest $request, ReportContent $reports): JsonResponse
    {
        $outcome = $reports->byMember($request->viewer(), $request->subject(), $request->reason(), $request->details());

        return $this->data(['reported' => true, 'reason' => $request->reason()->value], $outcome->created ? 201 : 200);
    }
}
