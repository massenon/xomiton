<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Content\ConversationsBd\ImportConversationsBd;
use Illuminate\Database\Seeder;

/**
 * P20-05 : casting, bibliothèque de scènes et 12 conversations modèles en BD — même import que la commande
 * `xomiton:conversations-bd:import`. Seeder de base (DatabaseSeeder::REFERENCE_SEEDERS) : il tourne aussi en production
 * par `xomiton:install`. Un modèle n'est publié que si toutes ses images existent (sinon brouillon).
 */
class ConversationsBdSeeder extends Seeder
{
    public function run(ImportConversationsBd $import): void
    {
        $import();
    }
}
