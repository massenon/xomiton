<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Application\Community\Suggestions\Suggestions;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use App\Support\NamedRateLimit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Boîte à suggestions (P18-14, docs/API.md §16 bis) : `GET suggestions/options` (catégories, limites) et
 * `POST suggestions` (`category`, `message` ≤ 1000, `is_anonymous`) → 201 `{id, status: "nouvelle"}`. Exige
 * `social.access` ; limiteur `suggestion` par compte et par jour, compté après validation.
 */
final class SuggestionController extends ApiController
{
    public function options(Request $request): JsonResponse
    {
        abort_unless($this->user($request)->can('social.access'), 403);

        return $this->data(Suggestions::options());
    }

    public function store(Request $request, Suggestions $suggestions): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('social.access'), 403);

        /** @var array{category: string, message: string, is_anonymous?: bool} $data */
        $data = $request->validate(Suggestions::rules(), [], [
            'category' => (string) __('community.suggestions.fields.category'),
            'message' => (string) __('community.suggestions.fields.message'),
        ]);

        if (($retryAfter = NamedRateLimit::attempt('suggestion', $request)) !== null) {
            throw new ThrottleRequestsException((string) __('auth.throttle', ['seconds' => $retryAfter]), null, ['Retry-After' => (string) $retryAfter]);
        }

        $suggestion = $suggestions->submit($user, $data);

        return $this->created(['id' => (int) $suggestion->getKey(), 'status' => $suggestion->suggestionStatus()->value]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
