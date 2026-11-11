<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Retention\AnonymizeDeletedAccounts;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class AnonymizeAccountsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'accounts:anonymize
        {--dry-run : Compte les comptes concernés sans rien modifier}';

    protected $description = 'Anonymise les comptes supprimés logiquement depuis plus de 30 jours (pseudo membre-supprimé-<id>, irréversible)';

    public function handle(AnonymizeDeletedAccounts $rule): int
    {
        if ($this->option('dry-run')) {
            $pending = $rule->pending()->count();

            $this->line(sprintf(
                'Simulation : %s (supprimé%s depuis plus de %d jours). Aucune modification.',
                $this->countLabel($pending, 'compte à anonymiser', 'comptes à anonymiser'),
                $pending > 1 ? 's' : '',
                $rule->graceDays(),
            ));

            return self::SUCCESS;
        }

        $count = $rule->run();

        $this->info(sprintf(
            '%s (supprimé%s depuis plus de %d jours).',
            $this->countLabel($count, 'compte anonymisé', 'comptes anonymisés'),
            $count > 1 ? 's' : '',
            $rule->graceDays(),
        ));

        return self::SUCCESS;
    }
}
