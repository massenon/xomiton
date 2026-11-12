<?php

declare(strict_types=1);

namespace App\Console\Commands\Content;

use App\Application\Content\ViolencesNumeriques\ImportViolencesNumeriques;
use App\Application\Content\ViolencesNumeriques\PublishComicsToFeed;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * P19-06 : importe (ou réaligne) les contenus des violences numériques — 6 catégories, fiches, FAQ des fiches et BD
 * `vn0X` — depuis `database/seeders/data/content/violences_numeriques/` (ImportViolencesNumeriques : rejouable sans
 * doublon, références juridiques de l'administration jamais touchées). `--publish` annonce ensuite chaque BD publique
 * une seule fois dans le fil social (PublishComicsToFeed : émetteur officiel, aucune notification).
 */
class ImportViolencesNumeriquesCommand extends Command
{
    protected $signature = 'xomiton:violences-numeriques:import
        {--publish : Publie aussi chaque BD publique une seule fois dans le fil social (compte officiel, sans notification)}';

    protected $description = 'Importe ou met à jour les fiches, FAQ et BD des violences numériques (idempotent) ; --publish les annonce dans le fil';

    public function handle(ImportViolencesNumeriques $import, PublishComicsToFeed $publish): int
    {
        try {
            $report = $import();
        } catch (RuntimeException $error) {
            $this->components->error('Import interrompu : '.$error->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Violences numériques : %d catégories, %d fiches, %d sujets à venir, %d questions, %d BD (%d créations, %d mises à jour).',
            $report['categories'], $report['fiches'], $report['upcoming'], $report['faqs'], $report['comics'], $report['created'], $report['updated'],
        ));

        if (! $this->option('publish')) {
            return self::SUCCESS;
        }

        $result = $publish();
        $this->components->info(sprintf(
            'Fil social : %d BD publiées%s, %d déjà publiées ou non publiques.',
            count($result['published']),
            $result['published'] === [] ? '' : ' ('.implode(', ', $result['published']).')',
            count($result['skipped']),
        ));

        return self::SUCCESS;
    }
}
