<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Retention\PurgeAuditLogs;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PurgeAuditLogsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'audit:purge
        {--days= : Rétention en jours (7 à 3650) ; sinon le paramètre audit_log_days de l’administration, sinon xomiton.retention.audit_log_days}';

    protected $description = 'Supprime les entrées du journal d’audit au-delà de la rétention configurée (90 jours par défaut)';

    public function handle(PurgeAuditLogs $rule): int
    {
        $option = $this->option('days');

        if ($option !== null && (! is_numeric($option) || (int) $option != $option)) {
            $this->error('L’option --days attend un nombre entier de jours.');

            return self::INVALID;
        }

        try {
            $days = $rule->resolveDays($option === null ? null : (int) $option);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $deleted = $rule->run($days);

        $this->info(sprintf(
            '%s (plus de %d jours).',
            $this->countLabel($deleted, 'entrée du journal d’audit purgée', 'entrées du journal d’audit purgées'),
            $days,
        ));

        return self::SUCCESS;
    }
}
