<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Contenu signalé à la modération (FR-SOC-06, RG-16 ; P3-08) : identifiant du signalement, nature du contenu
 * (valeur de ModerationKind), motif ; jamais l'extrait signalé. `reporterUserId` est nul pour un signalement anonyme.
 */
final readonly class ContentReported
{
    use Dispatchable;

    public function __construct(
        public int $reportId,
        public string $kind,
        public string $reason,
        public ?int $reporterUserId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
