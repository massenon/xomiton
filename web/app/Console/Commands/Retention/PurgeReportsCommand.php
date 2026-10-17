<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Retention\PurgeExpiredReports;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class PurgeReportsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'reports:purge';

    protected $description = 'Supprime du disque les fichiers de generated_reports au-delà d’expires_at ; la ligne est marquée si le journal d’audit la référence, supprimée sinon';

    public function handle(PurgeExpiredReports $rule): int
    {
        $result = $rule->run();

        $this->info(sprintf(
            '%s sur le disque « %s », %s, %s.',
            $this->countLabel($result->filesDeleted, 'fichier de rapport supprimé', 'fichiers de rapport supprimés'),
            $result->disk,
            $this->countLabel($result->rowsMarked, 'rapport marqué (référencé par le journal)', 'rapports marqués (référencés par le journal)'),
            $this->countLabel($result->rowsDeleted, 'rapport supprimé', 'rapports supprimés'),
        ));

        return self::SUCCESS;
    }
}
