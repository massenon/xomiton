<?php

declare(strict_types=1);

namespace App\Console\Commands\Moderation;

use App\Application\Moderation\ReportPrioritizer;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

/**
 * P18-04 : recalcule le score de priorité de tous les contenus en attente de modération (App\Application\Moderation\
 * ReportPrioritizer). Rattrapage seulement (lancée par la migration qui ajoute `moderation_reports.priority`) : le score
 * est tenu à jour à chaque signalement et à chaque décision.
 */
class PrioritizeReportsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'moderation:priorities';

    protected $description = 'Recalcule le score de priorité des contenus en attente de modération';

    public function handle(ReportPrioritizer $prioritizer): int
    {
        $count = $prioritizer->refreshAll();
        $this->info($this->countLabel($count, 'contenu noté', 'contenus notés').'.');

        return self::SUCCESS;
    }
}
