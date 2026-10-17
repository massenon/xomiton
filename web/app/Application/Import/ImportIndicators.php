<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\IndicatorFrequency;
use App\Models\Indicator;

/**
 * indicateurs.json → indicators (code, label, def → definition, formula, source, freq → frequency,
 * target, unit, calc → calc_key, better, builtin → is_builtin ; un indicateur personnalisé vaut
 * is_builtin = false). Déjà semés : réalignés par code, `is_active` conservé.
 */
final class ImportIndicators extends AbstractImporter
{
    public static function collection(): string
    {
        return 'indicateurs';
    }

    protected function importRow(array $row, int $index): void
    {
        $code = mb_strtoupper($this->required($row['code'] ?? null, 'code', 10));
        $label = $this->required($row['label'] ?? null, 'label', 160);
        $frequencyLabel = (string) ($row['freq'] ?? $row['frequency'] ?? '');
        $frequency = IndicatorFrequency::fromPrototype($frequencyLabel)
            ?? $this->reject(sprintf('fréquence inconnue « %s »', $frequencyLabel));
        $unit = $this->text($row['unit'] ?? null, 5) ?? Indicator::UNIT_COUNT;

        if (! in_array($unit, [Indicator::UNIT_COUNT, Indicator::UNIT_PERCENT], true)) {
            $this->reject(sprintf('unité inconnue « %s »', $unit));
        }

        $indicator = Indicator::query()->firstOrNew(['code' => $code]);

        $indicator->fill([
            'label' => $label,
            'definition' => $this->text($row['def'] ?? $row['definition'] ?? null) ?? $label,
            'formula' => $this->text($row['formula'] ?? null, 255) ?? '',
            'source' => $this->text($row['source'] ?? null, 80) ?? '',
            'frequency' => $frequency,
            'target' => is_numeric($row['target'] ?? null) ? (float) $row['target'] : null,
            'unit' => $unit,
            'calc_key' => $this->text($row['calc'] ?? $row['calc_key'] ?? null, 40),
            'better' => ($row['better'] ?? Indicator::BETTER_UP) === Indicator::BETTER_DOWN ? Indicator::BETTER_DOWN : Indicator::BETTER_UP,
            'is_builtin' => (bool) ($row['builtin'] ?? $row['is_builtin'] ?? ! ($row['custom'] ?? false)),
            'position' => $index + 1,
        ]);

        if (! $indicator->exists) {
            $indicator->is_active = (bool) ($row['active'] ?? true);
        }

        $indicator->save();
        $this->track($indicator);
    }
}
