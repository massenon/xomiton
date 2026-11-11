<?php

declare(strict_types=1);

namespace App\Application\Newsletter\Listeners;

use App\Domain\Newsletter\Events\CampaignSent;
use App\Models\NewsletterCampaign;
use App\Support\Audit;

/**
 * CampaignSent → journal `newsletter.campaign_sent` avec les compteurs (P3-08, DEVELOPMENT_STANDARDS §2 « Journal d'audit »).
 * Enregistré par AppServiceProvider::DOMAIN_LISTENERS.
 */
final class LogCampaignSent
{
    public function handle(CampaignSent $event): void
    {
        $campaign = NewsletterCampaign::query()->find($event->campaignId);

        Audit::log('newsletter.campaign_sent', $campaign, sprintf(
            'campagne=%d ; destinataires=%d ; envoyes=%d ; echecs=%d ; termine_le=%s',
            $event->campaignId,
            $event->recipientsCount,
            $event->sentCount,
            $event->failedCount(),
            $event->sentAt->format(DATE_ATOM),
        ), null);
    }
}
