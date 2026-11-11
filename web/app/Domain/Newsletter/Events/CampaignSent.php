<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Campagne de newsletter entièrement envoyée (FR-COM-02, RG-11 ; P3-08) : identifiant et compteurs, pour le journal
 * d'audit et les statistiques.
 */
final readonly class CampaignSent
{
    use Dispatchable;

    public function __construct(
        public int $campaignId,
        public int $recipientsCount,
        public int $sentCount,
        public DateTimeImmutable $sentAt,
    ) {}

    public function failedCount(): int
    {
        return max(0, $this->recipientsCount - $this->sentCount);
    }
}
