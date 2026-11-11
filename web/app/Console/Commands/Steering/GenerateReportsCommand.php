<?php

declare(strict_types=1);

namespace App\Console\Commands\Steering;

use App\Application\Steering\Reports\RunScheduledReports;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class GenerateReportsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'reports:generate';

    protected $description = 'Enfile la génération des rapports des planifications échues (report_schedules) ; l’envoi par e-mail suit chaque fichier';

    public function handle(RunScheduledReports $runner): int
    {
        $result = $runner->run();

        $this->info(sprintf(
            '%s échue(s) : %s enfilé(s)%s.',
            $this->countLabel($result['schedules'], 'planification', 'planifications'),
            $this->countLabel($result['reports'], 'rapport', 'rapports'),
            $result['failed'] > 0 ? ', '.$this->countLabel($result['failed'], 'échec', 'échecs') : '',
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
