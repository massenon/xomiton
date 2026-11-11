<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Feed;

use App\Application\Moderation\ReportContent;
use App\Http\Requests\Api\Feed\ReportContentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

/**
 * Signalement de contenu à la modération (P7-02, FR-SOC-06, RG-16) : `POST posts/{ulid}/report`,
 * `POST comments/{id}/report`. 201 au premier signalement, 200 si la personne l'avait déjà signalé (rien n'est compté
 * deux fois). La réponse ne dit jamais si le contenu a été masqué ni combien de personnes l'ont signalé.
 */
final class ContentReportController extends FeedApiController
{
    public function post(ReportContentRequest $request, Post $post, ReportContent $reports): JsonResponse
    {
        $outcome = $reports->byMember($this->viewer($request), $request->visiblePost(), $request->reason(), $request->details());

        return $this->data(['reported' => true, 'reason' => $request->reason()->value], $outcome->created ? 201 : 200);
    }

    public function comment(ReportContentRequest $request, Comment $comment, ReportContent $reports): JsonResponse
    {
        $outcome = $reports->byMember($this->viewer($request), $comment, $request->reason(), $request->details());

        return $this->data(['reported' => true, 'reason' => $request->reason()->value], $outcome->created ? 201 : 200);
    }
}
