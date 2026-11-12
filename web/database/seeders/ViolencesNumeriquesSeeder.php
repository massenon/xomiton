<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Content\ViolencesNumeriques\ImportViolencesNumeriques;
use Illuminate\Database\Seeder;

/**
 * P19-06 : contenus des violences numériques (catégories, fiches, FAQ des fiches, BD `vn0X`) — même import que la
 * commande `xomiton:violences-numeriques:import`. Seeder de base (DatabaseSeeder::REFERENCE_SEEDERS) : il tourne aussi en
 * production par `xomiton:install`. Il ne publie rien dans le fil social (option `--publish` de la commande).
 */
class ViolencesNumeriquesSeeder extends Seeder
{
    public function run(ImportViolencesNumeriques $import): void
    {
        $import();
    }
}
