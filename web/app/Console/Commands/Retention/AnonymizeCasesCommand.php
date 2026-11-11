<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Retention\AnonymizeClosedCases;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class AnonymizeCasesCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'cases:anonymize
        {--dry-run : Compte les dossiers concernés sans rien modifier}';

    protected $description = 'Anonymise les dossiers clos ou archivés depuis plus de 5 ans (notes, fiches et suivis remplacés par [anonymisé], irréversible)';

    public function handle(AnonymizeClosedCases $rule): int
    {
        if ($this->option('dry-run')) {
            $pending = $rule->pending()->count();

            $this->line(sprintf(
                'Simulation : %s (clos ou archivés depuis plus de %d ans). Aucune modification.',
                $this->countLabel($pending, 'dossier à anonymiser', 'dossiers à anonymiser'),
                $rule->years(),
            ));

            return self::SUCCESS;
        }

        $count = $rule->run();

        $this->info(sprintf(
            '%s (clos ou archivés depuis plus de %d ans).',
            $this->countLabel($count, 'dossier anonymisé', 'dossiers anonymisés'),
            $rule->years(),
        ));

        return self::SUCCESS;
    }
}
