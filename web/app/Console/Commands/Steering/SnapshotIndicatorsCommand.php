<?php

declare(strict_types=1);

namespace App\Console\Commands\Steering;

use App\Application\Steering\Period;
use App\Application\Steering\SnapshotIndicators;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SnapshotIndicatorsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'indicators:snapshot
        {--month= : Mois civil à calculer (AAAA-MM) ; par défaut le mois précédent}';

    protected $description = 'Calcule les indicateurs intégrés du mois écoulé (national et par département) et les enregistre dans indicator_values (idempotent, valeurs manuelles conservées)';

    public function handle(SnapshotIndicators $snapshot): int
    {
        $month = $this->option('month');

        try {
            $period = $month === null ? Period::previousMonth() : Period::fromMonthString((string) $month);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $result = $snapshot->run($period);

        $this->info(sprintf(
            'Instantané %s : %s (%s, %s), %s sans donnée, %s.',
            $period->label(),
            $this->countLabel($result->recorded, 'valeur enregistrée', 'valeurs enregistrées'),
            $this->countLabel($result->indicators, 'indicateur', 'indicateurs'),
            $this->countLabel($result->zones, 'département', 'départements'),
            $this->countLabel($result->withoutData, 'calcul', 'calculs'),
            $this->countLabel($result->manualKept, 'valeur manuelle conservée', 'valeurs manuelles conservées'),
        ));

        foreach ($result->withoutDataReasons as $code => $reason) {
            $this->line(sprintf('  %s sans valeur nationale : %s.', $code, $reason));
        }

        foreach ($result->unsupported as $code => $reason) {
            $this->warn(sprintf('%s non calculable : %s.', $code, $reason));
        }

        return self::SUCCESS;
    }
}
