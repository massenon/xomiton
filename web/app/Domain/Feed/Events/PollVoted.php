<?php

declare(strict_types=1);

namespace App\Domain\Feed\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Vote enregistré sur un sondage du fil (FR-SOC-02 ; P3-08). Identifiants seulement. Aucun écouteur ne journalise le
 * choix d'une personne (vie privée) : les compteurs et la clôture (`PollEnded`) sont branchés par P7.
 *
 * @param  list<int>  $optionIds
 */
final readonly class PollVoted
{
    use Dispatchable;

    /**
     * @param  list<int>  $optionIds
     */
    public function __construct(
        public int $pollId,
        public int $postId,
        public int $userId,
        public array $optionIds,
        public DateTimeImmutable $occurredAt,
    ) {}
}
