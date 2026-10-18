<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Account\PersonalDataExports;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

/**
 * Lot api-me (P6-06) : efface les exports RGPD dont le lien a expiré (`xomiton.accounts.export_link_hours`). Planifiée
 * chaque jour à 03:30 avec les autres purges de fichiers (routes/console.php).
 */
class PurgePersonalExportsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'accounts:purge-exports';

    protected $description = 'Supprime les exports RGPD (POST me/export) dont le lien de téléchargement a expiré';

    public function handle(PersonalDataExports $exports): int
    {
        $count = $exports->purgeExpired();

        $this->info(sprintf('%s (lien de %d h expiré).', $this->countLabel($count, 'export supprimé', 'exports supprimés'), PersonalDataExports::linkHours()));

        return self::SUCCESS;
    }
}
