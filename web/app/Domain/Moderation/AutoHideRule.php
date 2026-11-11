<?php

declare(strict_types=1);

namespace App\Domain\Moderation;

/**
 * RG-16 (proposée, à valider ; P7-01) : un contenu signalé par N personnes distinctes est masqué en attendant la
 * modération. N est paramétrable (`config('xomiton.feed.auto_hide_after_reports')`, 3 par défaut, surcharge
 * `Settings` `moderation_auto_hide_reports`) ; 0 désactive le masquage automatique. Seuls les signalements **en attente**
 * comptent : après une décision « aucune action » (signalements rejetés), il faut N nouveaux signalements.
 */
final readonly class AutoHideRule
{
    public function __construct(public int $threshold) {}

    public function isEnabled(): bool
    {
        return $this->threshold > 0;
    }

    public function shouldHide(int $pendingReports, bool $isPublished): bool
    {
        return $this->isEnabled() && $isPublished && $pendingReports >= $this->threshold;
    }
}
