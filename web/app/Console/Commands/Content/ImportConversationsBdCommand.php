<?php

declare(strict_types=1);

namespace App\Console\Commands\Content;

use App\Application\Content\ConversationsBd\ImportConversationsBd;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * P20-05 : importe (ou réaligne) le casting, la bibliothèque de scènes et les 12 conversations modèles en BD depuis
 * `database/seeders/data/content/conversations_bd/` (ImportConversationsBd : rejouable sans doublon, grammaire des bulles
 * vérifiée avant toute écriture, contenus et statuts retouchés dans l'administration jamais réalignés). À rejouer après
 * l'ajout des illustrations (`public/assets/images/conversations-bd/<slug>/case-N.webp`) : les brouillons complets sont
 * alors publiés.
 */
class ImportConversationsBdCommand extends Command
{
    protected $signature = 'xomiton:conversations-bd:import';

    protected $description = 'Importe ou met à jour les conversations en BD modèles, leurs scènes et le casting (idempotent)';

    public function handle(ImportConversationsBd $import): int
    {
        try {
            $report = $import();
        } catch (RuntimeException $error) {
            $this->components->error('Import interrompu : '.$error->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Conversations en BD : %d personnages, %d scènes, %d conversations (%d publiées, %d brouillons en attente d’images ; %d créations, %d mises à jour, %d retouchées dans l’administration et laissées telles quelles).',
            $report['characters'], $report['scenes'], $report['conversations'], $report['published'], $report['drafts'], $report['created'], $report['updated'], $report['kept'],
        ));

        return self::SUCCESS;
    }
}
