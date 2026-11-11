<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Feed;

use App\Application\Feed\Queries\FeedQuery;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Feed\PostResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Socle des contrôleurs du fil (P7-02, docs/API.md §8) : la personne connectée, la publication de la route **visible**
 * par elle (404 sinon, jamais 403 : on ne révèle pas qu'une publication existe) et la réponse d'une publication avec
 * l'état propre à la personne (j'aime, enregistrée, ses votes).
 */
abstract class FeedApiController extends ApiController
{
    public function __construct(protected readonly FeedQuery $feed) {}

    protected function viewer(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @throws NotFoundHttpException
     */
    protected function visible(Request $request, Post $post): Post
    {
        if (! $this->viewer($request)->can('view', $post)) {
            throw new NotFoundHttpException;
        }

        return $post;
    }

    protected function postResponse(Request $request, Post $post, int $status = 200): JsonResponse
    {
        $resource = PostResource::make($this->feed->loadViewerState($post, $this->viewer($request)));

        return $status === 201
            ? $this->created($resource, route('api.posts.show', ['post' => $post->ulid]))
            : $this->respond($resource, $status);
    }
}
