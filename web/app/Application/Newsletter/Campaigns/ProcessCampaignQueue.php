<?php

declare(strict_types=1);

namespace App\Application\Newsletter\Campaigns;

use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignBatch;
use App\Models\NewsletterCampaign;

/**
 * Passage de la commande `newsletter:process` (P14-03, chaque minute) et de « Traiter la file d'envoi » : démarre les
 * campagnes planifiées échues puis confie un lot à la file `database` (SendCampaignBatch) pour chaque campagne en cours,
 * dans la limite de 200 e-mails par minute en tout (NewsletterCampaign::BATCH_SIZE, `xomiton.newsletter.batch_size`).
 * Aucun SMTP ici : appelé depuis une requête web, il ne fait que déposer des jobs.
 */
final class ProcessCampaignQueue
{
    public function __construct(private readonly CampaignSender $sender) {}

    /**
     * @return array{started: int, batches: int, queued: int}
     */
    public function run(bool $sync = false): array
    {
        $started = 0;
        $batches = 0;
        $queued = 0;
        $budget = self::budget();

        foreach (NewsletterCampaign::query()->due()->orderBy('scheduled_at')->orderBy('id')->get() as $campaign) {
            $started += $this->sender->start($campaign) ? 1 : 0;
        }

        foreach (NewsletterCampaign::query()->withStatus(CampaignStatus::Sending)->orderBy('started_at')->orderBy('id')->get() as $campaign) {
            if ($budget <= 0) {
                break;
            }

            $size = min($budget, max(1, $this->sender->pendingCount($campaign)));
            $budget -= $size;
            $batches++;
            $queued += $size;

            $sync ? SendCampaignBatch::dispatchSync((int) $campaign->getKey(), $size) : SendCampaignBatch::dispatch((int) $campaign->getKey(), $size);
        }

        return ['started' => $started, 'batches' => $batches, 'queued' => $queued];
    }

    public static function budget(): int
    {
        return max(1, (int) config('xomiton.newsletter.batch_size', NewsletterCampaign::BATCH_SIZE));
    }
}
