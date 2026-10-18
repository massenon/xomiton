<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Domain\Feed\Events\PollVoted;
use App\Domain\Feed\Exceptions\NotAPoll;
use App\Domain\Feed\Exceptions\PollClosed;
use App\Domain\Feed\PollBallot;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Voter à un sondage ou retirer son vote (P7-02, FR-SOC-02, RG-09 ; prototype poll-vote.php). Le sondage est verrouillé
 * (`SELECT … FOR UPDATE`) le temps de calculer le bulletin (App\Domain\Feed\PollBallot) et d'ajuster les compteurs :
 *  - un nouvel envoi remplace le précédent ; les mêmes choix ne changent rien (idempotent, aucun compteur ne bouge) ;
 *  - retirer un vote absent ne fait rien ; un sondage terminé refuse vote et retrait (422 `poll_closed`) ;
 *  - PollVoted n'est émis que si le bulletin a changé, avec les identifiants seulement (jamais journalisé).
 * Les votes individuels ne sortent jamais : la réponse ne porte que les totaux et les choix de la personne elle-même.
 */
final class CastPollVote
{
    /**
     * @param  list<int>  $choices
     *
     * @throws NotAPoll|PollClosed
     * @throws InvalidArgumentException si le bulletin est invalide (validé en amont par PollVoteRequest)
     */
    public function vote(Post $post, User $user, array $choices): Poll
    {
        return $this->apply($post, $user, static fn (array $current, Poll $poll, array $optionIds): PollBallot => PollBallot::cast($current, $choices, $optionIds, $poll->multiple));
    }

    /**
     * @throws NotAPoll|PollClosed
     */
    public function retract(Post $post, User $user): Poll
    {
        return $this->apply($post, $user, static fn (array $current): PollBallot => PollBallot::retract($current));
    }

    /**
     * @param  callable(list<int>, Poll, list<int>): PollBallot  $ballotFor
     */
    private function apply(Post $post, User $user, callable $ballotFor): Poll
    {
        $pollId = Poll::query()->where('post_id', $post->getKey())->value('id');

        if ($pollId === null) {
            throw new NotAPoll;
        }

        /** @var array{0: Poll, 1: PollBallot} $result */
        $result = DB::transaction(function () use ($pollId, $user, $ballotFor): array {
            /** @var Poll $poll */
            $poll = Poll::query()->lockForUpdate()->findOrFail($pollId);

            if (! $poll->isOpen()) {
                throw new PollClosed;
            }

            /** @var list<int> $optionIds */
            $optionIds = PollOption::query()->where('poll_id', $poll->getKey())->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            /** @var list<int> $current */
            $current = PollVote::query()->forVoter($poll, $user)->pluck('poll_option_id')->map(static fn ($id): int => (int) $id)->all();

            $ballot = $ballotFor($current, $poll, $optionIds);

            if ($ballot->changes()) {
                $this->record($poll, $user, $ballot);
            }

            return [$poll, $ballot];
        });

        [$poll, $ballot] = $result;

        if ($ballot->changes() && $ballot->choices !== []) {
            PollVoted::dispatch((int) $poll->getKey(), (int) $poll->post_id, (int) $user->getKey(), $ballot->choices, new DateTimeImmutable);
        }

        return $poll->refresh();
    }

    private function record(Poll $poll, User $user, PollBallot $ballot): void
    {
        if ($ballot->toRemove !== []) {
            PollVote::query()->forVoter($poll, $user)->whereIn('poll_option_id', $ballot->toRemove)->delete();
            PollOption::query()->whereIn('id', $ballot->toRemove)->where('votes_count', '>', 0)->decrement('votes_count');
        }

        if ($ballot->toAdd !== []) {
            $now = now();

            PollVote::query()->insert(array_map(static fn (int $optionId): array => [
                'poll_option_id' => $optionId,
                'poll_id' => $poll->getKey(),
                'user_id' => $user->getKey(),
                'created_at' => $now,
            ], $ballot->toAdd));
            PollOption::query()->whereIn('id', $ballot->toAdd)->increment('votes_count');
        }

        $votes = max(0, (int) $poll->votes_count + $ballot->votesDelta());
        $voters = max(0, (int) $poll->voters_count + $ballot->votersDelta());

        Poll::query()->whereKey($poll->getKey())->update(['votes_count' => $votes, 'voters_count' => $voters]);
    }
}
