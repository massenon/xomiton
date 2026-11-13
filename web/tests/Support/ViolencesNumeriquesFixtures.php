<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Content\ViolencesNumeriques\ViolencesNumeriquesSource;
use Illuminate\Support\Facades\File;

/**
 * Les 23 sujets sont produits (13/11/2026) : pour continuer à tester le comportement d'un sujet « à venir » (brouillon
 * vide, carte sans lien, hors plan du site), on importe depuis une copie des données où ces sujets repassent en
 * « a_venir », sans toucher aux vraies données.
 */
final class ViolencesNumeriquesFixtures
{
    public static function withUpcoming(string ...$slugs): void
    {
        $source = new ViolencesNumeriquesSource;
        $copy = sys_get_temp_dir().'/xomiton-vn-'.bin2hex(random_bytes(6));
        File::copyDirectory($source->directory(), $copy);

        $path = $copy.'/_categories.json';
        /** @var array{categories: list<array{topics: list<array<string, mixed>>}>} $data */
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($data['categories'] as $c => $category) {
            foreach ($category['topics'] as $t => $topic) {
                if (in_array($topic['slug'], $slugs, true)) {
                    $data['categories'][$c]['topics'][$t]['status'] = ViolencesNumeriquesSource::STATUS_UPCOMING;
                    unset($data['categories'][$c]['topics'][$t]['key']);
                }
            }
        }

        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        app()->instance(ViolencesNumeriquesSource::class, new ViolencesNumeriquesSource($copy));
    }
}
