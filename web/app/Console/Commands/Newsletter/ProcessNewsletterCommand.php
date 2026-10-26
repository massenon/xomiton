<?php

declare(strict_types=1);

namespace App\Console\Commands\Newsletter;

use App\Application\Newsletter\Campaigns\ProcessCampaignQueue;
use Illuminate\Console\Command;

/**
 * P14-03 : planifiée chaque minute (routes/console.php). Démarre les campagnes échues et dépose un lot
 * (SendCampaignBatch, 200 e-mails au plus par minute) par campagne en cours ; `--sync` exécute les lots sur place.
 */
class ProcessNewsletterCommand extends Command
{
    protected $signature = 'newsletter:process
        {--sync : Exécute les lots dans ce processus au lieu de les déposer dans la file}';

    protected $description = 'Démarre les campagnes newsletter échues et envoie leurs lots (200 e-mails par minute)';

    public function handle(ProcessCampaignQueue $process): int
    {
        $result = $process->run((bool) $this->option('sync'));

        $this->line(sprintf('Campagnes démarrées : %d ; lots : %d ; e-mails visés : %d.', $result['started'], $result['batches'], $result['queued']));

        return self::SUCCESS;
    }
}
