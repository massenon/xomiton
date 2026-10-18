<?php

declare(strict_types=1);

namespace App\Http\Resources\Feed;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sondage d'une publication (FR-SOC-02, RG-09) : question, réglages, échéance, totaux et **les seuls choix de la personne
 * qui lit** (`my_choices`). Les votes individuels ne sortent jamais. Résultats par réponse (nombre, pourcentage, réponse en
 * tête) montrés après le vote, à la clôture ou si l'auteur·rice l'a permis (`results_before_vote`) ; sinon `null`.
 *
 * @mixin Poll
 */
final class PollResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Poll $poll */
        $poll = $this->resource;
        $myChoices = $this->myChoices($poll, $request);
        $open = $poll->isOpen();
        $showResults = $poll->results_before_vote || ! $open || $myChoices !== [];
        $options = $poll->relationLoaded('options') ? $poll->options : $poll->options()->get();
        $total = (int) $poll->votes_count;
        $leading = $total > 0 ? (int) $options->max('votes_count') : null;

        return [
            'question' => $poll->question,
            'multiple' => $poll->multiple,
            'results_before_vote' => $poll->results_before_vote,
            'ends_at' => $poll->ends_at?->toIso8601String(),
            'closed' => ! $open,
            'closed_at' => $poll->closed_at?->toIso8601String(),
            'voters_count' => (int) $poll->voters_count,
            'votes_count' => $showResults ? $total : null,
            'results_visible' => $showResults,
            'has_voted' => $myChoices !== [],
            'my_choices' => $myChoices,
            'can_vote' => $open,
            'options' => $options->map(static fn (PollOption $option): array => [
                'id' => (int) $option->getKey(),
                'label' => $option->label,
                'position' => (int) $option->position,
                'votes_count' => $showResults ? (int) $option->votes_count : null,
                'percentage' => $showResults ? ($total === 0 ? 0.0 : round($option->votes_count * 100 / $total, 1)) : null,
                'leading' => $showResults ? ($leading !== null && $leading > 0 && (int) $option->votes_count === $leading) : null,
            ])->values()->all(),
        ];
    }

    /**
     * @return list<int>
     */
    private function myChoices(Poll $poll, Request $request): array
    {
        $viewer = $request->user('sanctum') ?? $request->user();

        if (! $viewer instanceof User) {
            return [];
        }

        $votes = $poll->relationLoaded('votes')
            ? $poll->votes
            : PollVote::query()->where('poll_id', $poll->getKey())->where('user_id', $viewer->getKey())->get();

        $ids = $votes->where('user_id', $viewer->getKey())->pluck('poll_option_id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();

        return array_values(array_unique($ids));
    }
}
