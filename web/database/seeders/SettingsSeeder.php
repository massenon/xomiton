<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Paramètres de la plateforme du lot 11 (DATABASE_DICTIONARY.md §10 `settings`) : les 11 clés de
 * prototype_social/data/settings.json et les clés par défaut des écrans d'administration du prototype,
 * définies dans database/seeders/data/platform/settings.php. Idempotent : type, groupe et visibilité sont réalignés
 * à chaque exécution ; la valeur n'est posée qu'à la création pour ne pas écraser les réglages de l'administration.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array{0: string, 1: string, 2: mixed, 3: bool}> $defaults */
        $defaults = require __DIR__.'/data/platform/settings.php';

        foreach ($defaults as $key => [$group, $type, $value, $isPublic]) {
            $setting = Setting::query()->firstOrNew(['key' => $key]);

            $setting->fill([
                'type' => $type,
                'group' => $group,
                'is_public' => $isPublic,
            ]);

            if (! $setting->exists) {
                $setting->value = $value;
            }

            $setting->save();
        }
    }
}
