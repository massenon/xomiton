<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Retention\PruneSyncData;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class PruneSyncDataCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'sync:prune';

    protected $description = 'Supprime les reçus de synchronisation de plus de 90 jours et révoque les appareils sans contact depuis plus de 180 jours';

    public function handle(PruneSyncData $rule): int
    {
        $result = $rule->run();

        $this->info(sprintf(
            '%s (plus de %d jours).',
            $this->countLabel($result->receiptsDeleted, 'reçu de synchronisation supprimé', 'reçus de synchronisation supprimés'),
            $result->receiptDays,
        ));

        $this->info(sprintf(
            '%s (sans contact depuis plus de %d jours).',
            $this->countLabel($result->devicesRevoked, 'appareil révoqué', 'appareils révoqués'),
            $result->deviceDays,
        ));

        return self::SUCCESS;
    }
}
