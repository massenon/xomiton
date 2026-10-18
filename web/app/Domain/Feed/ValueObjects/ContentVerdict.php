<?php

declare(strict_types=1);

namespace App\Domain\Feed\ValueObjects;

/**
 * Résultat du filtre de mots (P7-01, FR-SOC-06) : `blocked` refuse le texte, `flagged` le laisse passer en le marquant
 * pour relecture. Les termes trouvés ne sont jamais renvoyés à l'auteur·rice (la liste reste confidentielle) : ils ne
 * servent qu'au détail du signalement automatique, lu par la seule modération.
 */
final readonly class ContentVerdict
{
    /**
     * @param  list<string>  $blockingTerms
     * @param  list<string>  $flaggingTerms
     */
    public function __construct(
        public array $blockingTerms = [],
        public array $flaggingTerms = [],
    ) {}

    public static function clean(): self
    {
        return new self;
    }

    public function isBlocked(): bool
    {
        return $this->blockingTerms !== [];
    }

    public function isFlagged(): bool
    {
        return ! $this->isBlocked() && $this->flaggingTerms !== [];
    }

    public function isClean(): bool
    {
        return $this->blockingTerms === [] && $this->flaggingTerms === [];
    }

    /**
     * Fusion de deux verdicts (question et réponses d'un sondage, corps et texte alternatif).
     */
    public function merge(self $other): self
    {
        return new self(
            array_values(array_unique([...$this->blockingTerms, ...$other->blockingTerms])),
            array_values(array_unique([...$this->flaggingTerms, ...$other->flaggingTerms])),
        );
    }
}
