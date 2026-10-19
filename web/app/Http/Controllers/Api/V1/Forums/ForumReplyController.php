<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Forums;

use App\Application\Community\Forums\ForumDirectory;
use App\Application\Community\Forums\RemoveForumContent;
use App\Application\Community\Forums\ReplyToTopic;
use App\Http\Controllers\Api\V1\Community\CommunityApiController;
use App\Http\Requests\Api\Forums\StoreReplyRequest;
use App\Http\Resources\Forums\ForumReplyResource;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Réponses d'un sujet (P8-05, docs/API.md §16) : liste paginée de la plus ancienne à la plus récente
 * (`xomiton.forums.replies_per_page`, blocages comme le fil), réponse (201, `meta.replies_count` à jour) et suppression
 * (auteur·rice ou modération). Sujet ou réponse invisible → 404.
 */
final class ForumReplyController extends CommunityApiController
{
    public function index(Request $request, ForumTopic $topic, ForumDirectory $forums): JsonResponse
    {
        $viewer = $this->viewer($request);

        if (! $viewer->can('view', $topic)) {
            throw new NotFoundHttpException;
        }

        $paginator = $forums->replies($topic, $viewer, $this->perPage($request, (int) config('xomiton.forums.replies_per_page', 30)));

        return $this->page(
            $paginator,
            ForumReplyResource::collection(collect($paginator->items()))->resolve($request),
            ['replies_count' => (int) $topic->replies_count],
        );
    }

    public function store(StoreReplyRequest $request, ForumTopic $topic, ReplyToTopic $reply): JsonResponse
    {
        $created = $reply($request->visibleTopic(), $this->viewer($request), $request->body(), $request->anonymous());

        return $this->respond(ForumReplyResource::make($created), 201, ['replies_count' => (int) $topic->replies_count]);
    }

    public function destroy(Request $request, ForumReply $reply, RemoveForumContent $remove): Response
    {
        $viewer = $this->viewer($request);

        if (! $viewer->can('view', $reply)) {
            throw new NotFoundHttpException;
        }

        abort_unless($viewer->can('delete', $reply), 403);
        $remove->reply($reply, $viewer);

        return $this->noContent();
    }
}
