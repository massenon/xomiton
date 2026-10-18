<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Feed;

use App\Application\Feed\Queries\UserPostsQuery;
use App\Http\Requests\Api\Feed\ListFeedRequest;
use App\Http\Resources\Feed\PostResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/v1/users/{id}/posts (`?cursor=&per_page=&tag=`, P6-08, simulateur `profil`, docs/API.md §11) : onglet
 * « Publications » d'un profil (UserPostsQuery) — même format et même pagination par curseur que `GET feed` ; profil
 * invisible (privé, abonné·es seulement, blocage, compte suspendu ou supprimé) ou inconnu → 404, jamais 403.
 */
final class UserPostController extends FeedApiController
{
    public function __invoke(ListFeedRequest $request, string $user, UserPostsQuery $posts): JsonResponse
    {
        $viewer = $this->viewer($request);
        $owner = $posts->owner($viewer, (int) $user) ?? throw new NotFoundHttpException;
        $query = $posts->posts($viewer, $owner, $request->tag());

        return $this->paginated(
            $query->cursorPaginate($this->perPage($request, (int) config('xomiton.feed.per_page', 20))),
            PostResource::class,
            ['user_id' => (int) $owner->getKey(), 'tag' => $request->tag()?->value],
        );
    }
}
