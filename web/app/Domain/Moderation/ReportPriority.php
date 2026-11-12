<?php

declare(strict_types=1);

namespace App\Domain\Moderation;

use App\Enums\ContentReportReason;
use App\Enums\ModerationKind;

/**
 * Score de priorité d'un contenu signalé (P18-04) : plus il est élevé, plus le contenu remonte dans la file de
 * modération. Formule volontairement simple et lisible par l'équipe (docs/MODULE_SPECIFICATIONS.md §D, P18-04) :
 *
 *     score = gravité du motif le plus grave (5 à 50, ContentReportReason::severity())
 *           + nature du contenu (5 à 10, ModerationKind::priorityWeight())
 *           + 10 par personne distincte au-delà de la première, plafonné à 30
 *           + 5 par sanction déjà prononcée contre l'auteur·rice (récidive), plafonné à 20
 *
 * Score de 10 à 110. Niveau : **haute** à partir de 60 (p. ex. une menace dans une publication, ou un harcèlement signalé
 * par deux personnes), **moyenne** à partir de 35, **basse** en dessous.
 */
final readonly class ReportPriority
{
    public const HIGH = 60;

    public const MEDIUM = 35;

    public const PEOPLE_STEP = 10;

    public const PEOPLE_CAP = 30;

    public const RECIDIVISM_STEP = 5;

    public const RECIDIVISM_CAP = 20;

    public const LEVEL_HIGH = 'haute';

    public const LEVEL_MEDIUM = 'moyenne';

    public const LEVEL_LOW = 'basse';

    /**
     * @param  list<ContentReportReason>  $reasons  motifs des signalements en attente du contenu
     * @param  int  $people  personnes distinctes qui ont signalé (0 pour le seul filtre automatique)
     * @param  int  $sanctions  sanctions déjà prononcées contre l'auteur·rice (autres contenus)
     */
    public function __construct(
        public array $reasons,
        public int $people,
        public ModerationKind $kind,
        public int $sanctions,
    ) {}

    public function score(): int
    {
        $severity = 0;

        foreach ($this->reasons as $reason) {
            $severity = max($severity, $reason->severity());
        }

        if ($severity === 0) {
            $severity = ContentReportReason::Other->severity();
        }

        $people = min(self::PEOPLE_CAP, self::PEOPLE_STEP * max(0, $this->people - 1));
        $recidivism = min(self::RECIDIVISM_CAP, self::RECIDIVISM_STEP * max(0, $this->sanctions));

        return $severity + $this->kind->priorityWeight() + $people + $recidivism;
    }

    public static function level(int $score): string
    {
        return match (true) {
            $score >= self::HIGH => self::LEVEL_HIGH,
            $score >= self::MEDIUM => self::LEVEL_MEDIUM,
            default => self::LEVEL_LOW,
        };
    }

    /** Couleur du badge (sens, jamais décorative). */
    public static function badge(string $level): string
    {
        return match ($level) {
            self::LEVEL_HIGH => 'danger',
            self::LEVEL_MEDIUM => 'warning',
            default => 'neutral',
        };
    }
}
