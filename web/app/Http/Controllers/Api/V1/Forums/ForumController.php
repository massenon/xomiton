<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Forums;

use App\Application\Admin\Network\ModerateNetwork;
use App\Application\Community\Forums\AcceptForumReply;
use App\Application\Community\Forums\ForumDirectory;
use App\Application\Community\Forums\ForumLinks;
use App\Application\Community\Forums\RecordTopicView;
use App\Application\Community\Forums\RemoveForumContent;
use App\Application\Community\Forums\StartTopic;
use App\Domain\Community\Exceptions\ReplyNotAcceptable;
use App\Http\Controllers\Api\V1\Community\CommunityApiController;
use App\Http\Requests\Api\Forums\ListTopicsRequest;
use App\Http\Requests\Api\Forums\StoreTopicRequest;
use App\Http\Resources\Forums\ForumResource;
use App\Http\Resources\Forums\ForumTopicResource;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
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
     * Valider la réponse qui résout le sujet (P18-03, Q97) : auteur·rice du sujet ou modération ; `reply_id` doit être
     * une réponse publiée du sujet (sinon 422 `reply_not_acceptable`). Rend le sujet à jour.
     */
    public function accept(Request $request, ForumTopic $topic, AcceptForumReply $accept): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->visible($request, $topic);
        abort_unless($viewer->can('accept', $topic), 403);

        $data = $request->validate(['reply_id' => ['required', 'integer', 'min:1']]);
        $reply = ForumReply::query()->whereKey((int) $data['reply_id'])->where('topic_id', $topic->getKey())->first();

        if ($reply === null) {
            throw new ReplyNotAcceptable;
        }

        $accept->accept($topic, $reply, $viewer);
        $topic->loadMissing(['forum', 'user.profile', 'user.roles']);

        return $this->respond(ForumTopicResource::make($topic)->detailed());
    }

    /** Retirer la réponse validée (auteur·rice du sujet ou modération) ; idempotent. */
    public function unaccept(Request $request, ForumTopic $topic, AcceptForumReply $accept): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->visible($request, $topic);
        abort_unless($viewer->can('accept', $topic), 403);

        $accept->unaccept($topic, $viewer);
        $topic->loadMissing(['forum', 'user.profile', 'user.roles']);

        return $this->respond(ForumTopicResource::make($topic)->detailed());
    }

    /**
     * Épingler / désépingler (P18-11, modération `posts.moderate`) : `POST` épingle, `DELETE` désépingle ; idempotent.
     */
    public function pin(Request $request, ForumTopic $topic, ModerateNetwork $network): JsonResponse
    {
        return $this->flag($request, $topic, fn (User $viewer): bool => $network->pin($topic, $request->isMethod('post'), $viewer));
    }

    /** Verrouiller / déverrouiller (P18-11) : plus de réponse, le sujet reste lisible ; idempotent. */
    public function lock(Request $request, ForumTopic $topic, ModerateNetwork $network): JsonResponse
    {
        return $this->flag($request, $topic, fn (User $viewer): bool => $network->lock($topic, $request->isMethod('post'), $viewer));
    }

    /**
     * @param  callable(User): bool  $change
     */
    private function flag(Request $request, ForumTopic $topic, callable $change): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->visible($request, $topic);
        abort_unless($viewer->can('moderate', $topic), 403);
        $change($viewer);
        $topic->loadMissing(['forum', 'user.profile', 'user.roles']);

        return $this->respond(ForumTopicResource::make($topic)->detailed());
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
