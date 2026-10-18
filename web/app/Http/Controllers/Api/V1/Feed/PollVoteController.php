<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Feed;

use App\Application\Feed\CastPollVote;
use App\Http\Requests\Api\Feed\PollVoteRequest;
use App\Http\Resources\Feed\PollResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * `POST / DELETE posts/{ulid}/poll/vote` (P7-02, FR-SOC-02, RG-09) : voter (`{choices: []}`, remplace le vote précédent,
 * mêmes choix = sans effet) ou retirer son vote ; 200 avec le sondage (totaux et `my_choices` de la personne seulement),
 * 404 si la publication est invisible ou sans sondage, 422 `poll_closed` après l'échéance.
 */
final class PollVoteController extends FeedApiController
{
    public function store(PollVoteRequest $request, Post $post, CastPollVote $votes): JsonResponse
    {
        $poll = $votes->vote($request->visiblePost(), $this->viewer($request), $request->choices());

        return $this->respond(PollResource::make($poll->load('options')));
    }

    public function destroy(Request $request, Post $post, CastPollVote $votes): JsonResponse
    {
        Gate::forUser($this->viewer($request))->authorize('vote', $this->visible($request, $post));
        $poll = $votes->retract($post, $this->viewer($request));

        return $this->respond(PollResource::make($poll->load('options')));
    }
}
