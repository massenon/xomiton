<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Application\Community\Groups\GroupPostsQuery;
use App\Http\Resources\Feed\PostResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/groups/{ulid}/posts?cursor=&per_page= (P8-02, docs/API.md §13) : publications du groupe sous la forme du
 * fil (PostResource, pagination par curseur, docs/API.md §8) ; 404 si le groupe n'est pas lisible (privé et non
 * membre). Publier dans le groupe : `POST posts` avec `group` (§8.3). Commenter une publication de groupe est réservé
 * à ses membres (`can.comment` faux pour les autres, PostPolicy::comment).
 */
final class GroupPostController extends CommunityApiController
{
    public function index(Request $request, GroupPostsQuery $posts, Group $group): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->readable($request, $group);

        $paginator = $posts->for($group, $viewer)->cursorPaginate($this->perPage($request, (int) config('xomiton.feed.per_page', 20)));
        $items = self::forViewer(PostResource::collection(collect($paginator->items()))->resolve($request), $viewer, $group);

        return $this->data($items, 200, [
            'per_page' => $paginator->perPage(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
        ], [
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ]);
    }

    /**
     * `can.comment` des publications d'un groupe : membres seulement (prototype : « Rejoins le groupe pour publier et
     * commenter »).
     *
     * @param  array<array-key, mixed>  $items
     * @return list<array<string, mixed>>
     */
    public static function forViewer(array $items, User $viewer, Group $group): array
    {
        $member = $viewer->can('post', $group);

        return array_values(array_map(static function (mixed $item) use ($member): array {
            $item = (array) $item;
            $item['can'] = ['comment' => $member && (bool) ($item['can']['comment'] ?? false)] + (array) ($item['can'] ?? []);

            return $item;
        }, $items));
    }
}
