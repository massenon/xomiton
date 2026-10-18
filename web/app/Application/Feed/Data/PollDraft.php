<?php

declare(strict_types=1);

namespace App\Application\Feed\Data;

/**
 * Sondage à créer avec sa publication (FR-SOC-02, RG-09) : question 5–200 car., 2 à 6 réponses distinctes (≤ 80 car.),
 * choix multiple, résultats avant le vote, durée en jours (`xomiton.feed.poll_durations_days`, null = sans fin).
 */
final readonly class PollDraft
{
    /**
     * @param  list<string>  $options
     */
    public function __construct(
        public string $question,
        public array $options,
        public bool $multiple = false,
        public bool $resultsBeforeVote = false,
        public ?int $durationDays = null,
    ) {}
}
