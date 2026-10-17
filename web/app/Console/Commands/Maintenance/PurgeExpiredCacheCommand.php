<?php

declare(strict_types=1);

namespace App\Console\Commands\Maintenance;

use App\Application\Maintenance\PurgeExpiredCache;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class PurgeExpiredCacheCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'cache:purge-expired';

    protected $description = 'Supprime les fichiers expirés du cache file (storage/framework/cache/data) en lisant leur horodatage d’expiration ; sans effet pour les autres pilotes';

    public function handle(PurgeExpiredCache $rule): int
    {
        $result = $rule->run();

        if ($result->skipped) {
            $this->line(sprintf(
                'Le cache « %s » utilise le pilote « %s » : il purge lui-même ses entrées expirées, rien à faire.',
                $result->store,
                $result->driver,
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s sur %s dans %s.',
            $this->countLabel($result->deleted, 'fichier de cache expiré supprimé', 'fichiers de cache expirés supprimés'),
            $this->countLabel($result->scanned, 'fichier examiné', 'fichiers examinés'),
            $rule->directory(),
        ));

        return self::SUCCESS;
    }
}
