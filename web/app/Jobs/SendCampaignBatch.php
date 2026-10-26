<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Newsletter\Campaigns\CampaignSender;
use App\Models\NewsletterCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Un lot d'une campagne newsletter (P14-03) : 200 e-mails au plus, un seul lot à la fois par campagne
 * (`ShouldBeUnique`, verrou du cache `file`). Déposé chaque minute par `newsletter:process`, exécuté par la seconde ligne
 * cron (`queue:work --stop-when-empty --max-time=50`). Un lot interrompu n'est jamais rejoué tel quel : les destinataires
 * réservés avant l'envoi ne sont pas renvoyés (au plus un envoi par personne et par campagne).
 */
final class SendCampaignBatch implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 55;

    public int $uniqueFor = 120;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $limit = NewsletterCampaign::BATCH_SIZE,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->campaignId;
    }

    public function handle(CampaignSender $sender): void
    {
        $campaign = NewsletterCampaign::query()->find($this->campaignId);

        if ($campaign !== null) {
            $sender->sendBatch($campaign, $this->limit);
        }
    }
}
