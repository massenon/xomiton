<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Content\ConversationsBd\ConversationsBdSource;
use App\Application\Content\ConversationsBd\ImportConversationsBd;
use Illuminate\Support\Facades\File;

/**
 * Les 60 illustrations existent depuis le 14/11/2026 : l'import publie les 12 modèles. Pour tester le cycle de vie
 * d'un brouillon (images manquantes, publication à l'arrivée des images), on importe depuis une copie des données où
 * les images des scènes pointent vers un dossier absent, sauf pour les modèles listés.
 */
final class ConversationsBdFixtures
{
    /**
     * @param  list<string>  $withImages  clés de modèles (m01…) dont les images restent présentes
     * @param  (callable(string): void)|null  $alter  modification supplémentaire de la copie
     */
    public static function source(array $withImages = [], ?callable $alter = null): string
    {
        $dir = sys_get_temp_dir().'/bdc-'.uniqid();
        File::copyDirectory(database_path('seeders/data/content/conversations_bd'), $dir);

        /** @var array{scenes: list<array<string, mixed>>} $scenes */
        $scenes = json_decode((string) file_get_contents($dir.'/_scenes.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($scenes['scenes'] as $i => $scene) {
            $key = (string) $scene['key'];
            if (! in_array(substr($key, 0, 3), $withImages, true)) {
                $scenes['scenes'][$i]['image'] = 'assets/images/conversations-bd-absentes/'.$key.'.webp';
            }
        }

        file_put_contents($dir.'/_scenes.json', json_encode($scenes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        if ($alter !== null) {
            $alter($dir);
        }

        app()->instance(ConversationsBdSource::class, new ConversationsBdSource($dir));
        app()->forgetInstance(ImportConversationsBd::class);

        return $dir;
    }

    /** Supprime les copies temporaires (à appeler dans afterEach). */
    public static function cleanup(): void
    {
        foreach (glob(sys_get_temp_dir().'/bdc-*') ?: [] as $dir) {
            File::deleteDirectory($dir);
        }
    }
}
