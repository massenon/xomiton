<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Retention\PurgeStaleSessions;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class PurgeSessionsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'sessions:purge';

    protected $description = 'Supprime les sessions en base inactives depuis plus que la durée de session + 1 jour (D7)';

    public function handle(PurgeStaleSessions $rule): int
    {
        if (! $rule->tableExists()) {
            $this->warn(sprintf('La table « %s » n’existe pas : aucune session à purger.', $rule->table()));

            return self::SUCCESS;
        }

        if (config('session.driver') !== 'database') {
            $this->comment(sprintf('Le pilote de session est « %s » : la table est purgée par précaution.', (string) config('session.driver')));
        }

        $deleted = $rule->run();

        $this->info(sprintf(
            '%s (inactive%s depuis plus de %d min + %d jour%s).',
            $this->countLabel($deleted, 'session purgée', 'sessions purgées'),
            $deleted > 1 ? 's' : '',
            (int) config('session.lifetime', 120),
            (int) config('xomiton.retention.session_grace_days', 1),
            (int) config('xomiton.retention.session_grace_days', 1) > 1 ? 's' : '',
        ));

        return self::SUCCESS;
    }
}
