<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Import\PrototypeImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * `php artisan xomiton:import-prototype <dossier data/>` : importe les collections JSON du prototype
 * dans les tables de la phase 1 (IMPLEMENTATION_PLAN.md P1-13). La logique vit dans
 * App\Application\Import ; la commande lit les options, affiche l'avancement et le résumé par collection.
 */
final class ImportPrototypeCommand extends Command
{
    protected $signature = 'xomiton:import-prototype
        {directory : Dossier data/ du prototype (ex. ../prototype_social/data)}
        {--only=* : Collections à importer (users, annuaire, cases…), répétables ou séparées par des virgules}
        {--dry-run : Simule l’import : rien n’est écrit (transaction annulée), les rejets sont journalisés}
        {--with-demo : Importe aussi les contenus de démonstration et les contacts (données personnelles), même en production}';

    protected $description = 'Importe les collections JSON du prototype (prototype_social/data) dans les tables de la phase 1';

    public function handle(PrototypeImporter $importer): int
    {
        $directory = (string) $this->argument('directory');
        $only = $this->only();
        $dryRun = (bool) $this->option('dry-run');
        $withDemo = (bool) $this->option('with-demo');

        $this->components->info(sprintf(
            'Import du prototype depuis %s%s%s',
            $directory,
            $dryRun ? ' (simulation : aucune écriture)' : '',
            $withDemo ? ', contenus de démonstration inclus' : '',
        ));

        try {
            $report = $importer->import(
                directory: $directory,
                only: $only,
                dryRun: $dryRun,
                withDemo: $withDemo,
                progress: function (string $collection, array $counts): void {
                    $this->components->twoColumnDetail(
                        $collection,
                        $counts['skipped'] !== null
                            ? '<fg=yellow>ignorée</> : '.$counts['skipped']
                            : sprintf('%d créé(s), %d mis à jour, %s', $counts['created'], $counts['updated'], $counts['rejected'] > 0
                                ? sprintf('<fg=red>%d rejeté(s)</>', $counts['rejected'])
                                : '0 rejeté'),
                    );
                },
            );
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        } catch (Throwable $exception) {
            $this->components->error('Import interrompu, aucune écriture conservée : '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Collection', 'Créés', 'Mis à jour', 'Rejetés', 'Remarque'],
            array_map(
                static fn (string $collection, array $counts): array => [
                    $collection,
                    $counts['created'],
                    $counts['updated'],
                    $counts['rejected'],
                    $counts['skipped'] ?? '',
                ],
                array_keys($report->rows()),
                $report->rows(),
            ),
        );

        $totals = $report->totals();
        $this->components->info(sprintf(
            'Total : %d créé(s), %d mis à jour, %d rejeté(s), %d collection(s) ignorée(s). Journal : %s',
            $totals['created'],
            $totals['updated'],
            $totals['rejected'],
            $totals['skipped'],
            storage_path(PrototypeImporter::LOG_FILE),
        ));

        if ($dryRun) {
            $this->components->warn('Simulation : la transaction a été annulée, la base est inchangée.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function only(): array
    {
        $values = [];

        foreach ((array) $this->option('only') as $option) {
            foreach (explode(',', (string) $option) as $name) {
                $name = trim($name);

                if ($name !== '') {
                    $values[] = $name;
                }
            }
        }

        return array_values(array_unique($values));
    }
}
