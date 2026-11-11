<?php

declare(strict_types=1);

namespace App\Domain\Feed\ValueObjects;

/**
 * Résultat du filtre de contenus (P7-01, FR-SOC-06, D-IOS-9) : `blocked` refuse le texte, `hidden` le publie puis le
 * masque et le met en file de modération, `flagged` le laisse passer en le marquant pour relecture. Les termes trouvés ne
 * sont jamais renvoyés à l'auteur·rice (la liste reste confidentielle) : ils ne servent qu'au détail du signalement
 * automatique, lu par la seule modération. Les motifs de structure (coordonnées, liens) sont notés `#<catégorie>`.
 * `categories` : catégories touchées (insulte, haine, coordonnees, lien…), seules données du journal d'audit.
 */
final readonly class ContentVerdict
{
    /**
     * @param  list<string>  $blockingTerms
     * @param  list<string>  $flaggingTerms
     * @param  list<string>  $hidingTerms
     * @param  list<string>  $categories
     */
    public function __construct(
        public array $blockingTerms = [],
        public array $flaggingTerms = [],
        public array $hidingTerms = [],
        public array $categories = [],
    ) {}

    public static function clean(): self
    {
        return new self;
    }

    public function isBlocked(): bool
    {
        return $this->blockingTerms !== [];
    }

    /** Publié puis masqué (et signalé) par le filtre. */
    public function isHidden(): bool
    {
        return ! $this->isBlocked() && $this->hidingTerms !== [];
    }

    public function isFlagged(): bool
    {
        return ! $this->isBlocked() && $this->flaggingTerms !== [];
    }

    /** Un signalement automatique doit être ouvert (signaler ou masquer). */
    public function needsReview(): bool
    {
        return $this->isHidden() || $this->isFlagged();
    }

    public function isClean(): bool
    {
        return $this->blockingTerms === [] && $this->flaggingTerms === [] && $this->hidingTerms === [];
    }

    /**
     * Termes à écrire dans le détail du signalement automatique (masqués puis signalés).
     *
     * @return list<string>
     */
    public function reviewTerms(): array
    {
        return array_values(array_unique([...$this->hidingTerms, ...$this->flaggingTerms]));
    }

    /**
     * Fusion de deux verdicts (question et réponses d'un sondage, corps et texte alternatif).
     */
    public function merge(self $other): self
    {
        return new self(
            array_values(array_unique([...$this->blockingTerms, ...$other->blockingTerms])),
            array_values(array_unique([...$this->flaggingTerms, ...$other->flaggingTerms])),
            array_values(array_unique([...$this->hidingTerms, ...$other->hidingTerms])),
            array_values(array_unique([...$this->categories, ...$other->categories])),
        );
    }
}
