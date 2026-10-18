<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Feed;

use App\Http\Requests\Api\Feed\Concerns\FeedRequest;
use App\Models\Poll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /api/v1/posts/{ulid}/poll/vote (P7-02, FR-SOC-02, RG-09) : `{choices: [option_id, …]}` — au moins une réponse,
 * toutes du sondage, une seule en choix unique. Publication visible (404 sinon) ; le sondage absent ou terminé est traité
 * par App\Application\Feed\CastPollVote (404 `not_found`, 422 `poll_closed`).
 */
final class PollVoteRequest extends FormRequest
{
    use FeedRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('vote', $this->visiblePost());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'choices' => ['required', 'array', 'min:1', 'max:'.Poll::MAX_OPTIONS],
            'choices.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'choices.required' => (string) __('feed.poll.choose_one'),
            'choices.min' => (string) __('feed.poll.choose_one'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $poll = Poll::query()->where('post_id', $this->visiblePost()->getKey())->first();
            $choices = $this->input('choices');

            if ($poll === null || ! is_array($choices) || $validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = $poll->options()->pluck('id')->map(static fn ($id): int => (int) $id)->all();

            if (array_diff(array_map('intval', $choices), $ids) !== []) {
                $validator->errors()->add('choices', (string) __('feed.api.choice_unknown'));
            } elseif (! $poll->multiple && count($choices) > 1) {
                $validator->errors()->add('choices', (string) __('feed.api.single_choice'));
            }
        }];
    }

    /**
     * @return list<int>
     */
    public function choices(): array
    {
        return array_values(array_map('intval', (array) $this->validated('choices')));
    }
}
