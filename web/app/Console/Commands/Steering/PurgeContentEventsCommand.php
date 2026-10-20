<?php

declare(strict_types=1);

namespace App\Console\Commands\Steering;

use App\Application\Steering\ContentEvents;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class PurgeContentEventsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'content-events:purge';

    protected $description = 'Supprime les compteurs d’événements de contenu au-delà de la rétention (30 jours par défaut)';

    public function handle(ContentEvents $events): int
    {
        $deleted = $events->purge();

        $this->info(sprintf('%s au-delà de %d jours.', $this->countLabel($deleted, 'compteur supprimé', 'compteurs supprimés'), ContentEvents::retentionDays()));

        return self::SUCCESS;
    }
}
