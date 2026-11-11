<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Forum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Forums du lot 5, repris du prototype (prototype_social/inc/data.php : x_forums()).
 * Idempotent : la clé courte `f1`…`f5` identifie chaque forum ; nom, description, icône et position
 * sont réalignés à chaque exécution, les compteurs (recalculés par les actions) ne sont pas touchés.
 */
class ForumsSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string}> clé, nom, description, icône (x_forums()) */
    private const FORUMS = [
        ['f1', 'Parler librement', "Espace d'échange général, sans jugement.", 'bi-chat-heart'],
        ['f2', 'Droits & démarches', 'Questions juridiques, plaintes, recours.', 'bi-bank'],
        ['f3', 'Santé & bien-être', 'Santé mentale, santé sexuelle, hygiène menstruelle.', 'bi-heart-pulse'],
        ['f4', 'Sororité & entraide', 'Cercles de solidarité, témoignages positifs.', 'bi-people'],
        ['f5', 'Autonomisation', 'Emploi, finance, formations.', 'bi-graph-up-arrow'],
    ];

    public function run(): void
    {
        foreach (self::FORUMS as $position => [$key, $name, $description, $icon]) {
            Forum::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => $description,
                    'icon' => $icon,
                    'position' => $position,
                    'is_active' => true,
                ],
            );
        }
    }
}
