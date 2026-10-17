<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\IndicatorFrequency;
use App\Models\Indicator;
use Illuminate\Database\Seeder;

/**
 * Catalogue des indicateurs du lot 8, repris de prototype_social/data/indicateurs.json (16 indicateurs intégrés,
 * IND-09 = taux d'ouverture de la newsletter). Idempotent : rejouable sans doublon, les définitions sont
 * réalignées sur le prototype ; `is_active` n'est pas écrasé pour respecter les désactivations de l'administration.
 */
class IndicatorsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: int|float, 7: string, 8: string, 9: string}> $rows */
        $rows = require __DIR__.'/data/steering/indicators.php';

        foreach ($rows as $index => [$code, $label, $definition, $formula, $source, $frequency, $target, $unit, $calcKey, $better]) {
            $indicator = Indicator::query()->firstOrNew(['code' => $code]);

            $indicator->fill([
                'label' => $label,
                'definition' => $definition,
                'formula' => $formula,
                'source' => $source,
                'frequency' => IndicatorFrequency::fromPrototype($frequency) ?? IndicatorFrequency::Monthly,
                'target' => $target,
                'unit' => $unit,
                'calc_key' => $calcKey,
                'better' => $better,
                'is_builtin' => true,
                'position' => $index + 1,
            ]);

            if (! $indicator->exists) {
                $indicator->is_active = true;
            }

            $indicator->save();
        }
    }
}
