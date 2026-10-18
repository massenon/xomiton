<?php

declare(strict_types=1);

namespace App\Domain\Feed;

use InvalidArgumentException;

/**
 * Bulletin d'un sondage (P7-01, RG-09, FR-SOC-02) : à partir des options déjà choisies par la personne et de celles
 * qu'elle envoie, calcule les votes à ajouter et à retirer. Un nouvel envoi **remplace** le précédent (comme le prototype
 * `poll-vote.php`) ; renvoyer les mêmes choix ne change rien (vote idempotent, aucun compteur ne bouge). Pur et sans base.
 */
final readonly class PollBallot
{
    /**
     * @param  list<int>  $toAdd
     * @param  list<int>  $toRemove
     * @param  list<int>  $choices
     */
    private function __construct(
        public array $toAdd,
        public array $toRemove,
        public array $choices,
        public bool $isNewVoter,
    ) {}

    /**
     * @param  list<int>  $current  options déjà votées par la personne
     * @param  list<int>  $requested  options envoyées
     * @param  list<int>  $optionIds  options du sondage
     *
     * @throws InvalidArgumentException si aucun choix, une option étrangère au sondage, ou plusieurs choix en choix unique
     */
    public static function cast(array $current, array $requested, array $optionIds, bool $multiple): self
    {
        $choices = array_values(array_unique(array_map('intval', $requested)));
        sort($choices);

        if ($choices === []) {
            throw new InvalidArgumentException('A ballot needs at least one choice.');
        }

        if (array_diff($choices, $optionIds) !== []) {
            throw new InvalidArgumentException('A ballot choice does not belong to the poll.');
        }

        if (! $multiple && count($choices) > 1) {
            throw new InvalidArgumentException('A single-choice poll accepts one choice only.');
        }

        $current = array_values(array_unique(array_map('intval', $current)));

        return new self(
            array_values(array_diff($choices, $current)),
            array_values(array_diff($current, $choices)),
            $choices,
            $current === [],
        );
    }

    /**
     * Retrait du vote : toutes les options de la personne, sans erreur si elle n'avait pas voté.
     *
     * @param  list<int>  $current
     */
    public static function retract(array $current): self
    {
        return new self([], array_values(array_unique(array_map('intval', $current))), [], false);
    }

    public function changes(): bool
    {
        return $this->toAdd !== [] || $this->toRemove !== [];
    }

    /** Variation du nombre total de votes (somme des options). */
    public function votesDelta(): int
    {
        return count($this->toAdd) - count($this->toRemove);
    }

    /** Variation du nombre de participant·es : +1 au premier vote, −1 au retrait complet, 0 sinon. */
    public function votersDelta(): int
    {
        if ($this->choices === []) {
            return $this->toRemove === [] ? 0 : -1;
        }

        return $this->isNewVoter ? 1 : 0;
    }
}
