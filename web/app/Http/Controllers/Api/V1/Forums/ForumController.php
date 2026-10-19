<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Forums;

use App\Application\Community\Forums\ForumDirectory;
use App\Application\Community\Forums\ForumLinks;
use App\Application\Community\Forums\RecordTopicView;
use App\Application\Community\Forums\RemoveForumContent;
use App\Application\Community\Forums\StartTopic;
use App\Http\Controllers\Api\V1\Community\CommunityApiController;
use App\Http\Requests\Api\Forums\ListTopicsRequest;
use App\Http\Requests\Api\Forums\StoreTopicRequest;
use App\Http\Resources\Forums\ForumResource;
use App\Http\Resources\Forums\ForumTopicResource;
use App\Models\ForumTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/forums (P8-05, docs/API.md §16) : les 5 forums et leurs compteurs, « Sujets récents » (forum, recherche,
 * pagination par page, `xomiton.forums.topics_per_page`), ouverture d'un sujet (201 + Location), fiche (vue comptée une
 * fois par session ou par fenêtre, RecordTopicView) et suppression (auteur·rice ou modération). Sujet invisible → 404.
 */
final class ForumController extends CommunityApiController
{
    public function __construct(private readonly ForumDirectory $forums) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->viewer($request)->can('viewAny', ForumTopic::class), 403);

        return $this->respond(ForumResource::collection($this->forums->forums()));
    }

    public function topics(ListTopicsRequest $request): JsonResponse
    {
        $forum = $this->forums->forum($request->forumKey());
        $paginator = $this->forums->paginate(
            $this->viewer($request),
            $forum,
            $request->term(),
            $this->perPage($request, (int) config('xomiton.forums.topics_per_page', 6)),
        );

        return $this->page(
            $paginator,
            ForumTopicResource::collection(collect($paginator->items()))->resolve($request),
            ['forum' => $forum?->key],
        );
    }

    public function store(StoreTopicRequest $request, StartTopic $start): JsonResponse
    {
        $topic = $start($this->viewer($request), $request->toDraft());

        return $this->created(ForumTopicResource::make($topic)->detailed(), ForumLinks::show($topic));
    }

    public function show(Request $request, ForumTopic $topic, RecordTopicView $views): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->visible($request, $topic)->loadMissing(['forum', 'user.profile', 'user.roles']);
        $views($topic, $request, $viewer);

        return $this->respond(ForumTopicResource::make($topic)->detailed());
    }

    public function destroy(Request $request, ForumTopic $topic, RemoveForumContent $remove): Response
    {
        $viewer = $this->viewer($request);
        abort_unless($viewer->can('delete', $this->visible($request, $topic)), 403);
        $remove->topic($topic, $viewer);

        return $this->noContent();
    }

    /**
     * @throws NotFoundHttpException
     */
    private function visible(Request $request, ForumTopic $topic): ForumTopic
    {
        if (! $this->viewer($request)->can('view', $topic)) {
            throw new NotFoundHttpException;
        }

        return $topic;
    }
}
