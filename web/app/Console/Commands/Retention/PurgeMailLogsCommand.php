<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Retention\PurgeMailLogs;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class PurgeMailLogsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'mail:purge-logs';

    protected $description = 'Supprime les journaux d’envoi d’e-mails (mail_logs) de plus de 180 jours';

    public function handle(PurgeMailLogs $rule): int
    {
        $deleted = $rule->run();

        $this->info(sprintf(
            '%s (plus de %d jours).',
            $this->countLabel($deleted, 'journal d’envoi purgé', 'journaux d’envoi purgés'),
            $rule->days(),
        ));

        return self::SUCCESS;
    }
}
