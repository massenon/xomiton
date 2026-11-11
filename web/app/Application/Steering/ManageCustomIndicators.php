<?php

declare(strict_types=1);

namespace App\Application\Steering;

use App\Enums\IndicatorFrequency;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs personnalisés (P10-06) : création avec une valeur saisie à la main, nouvelle valeur, suppression. Les 16
 * indicateurs intégrés (`is_builtin`) ne se suppriment jamais, ni ne se modifient ici. Chaque action est journalisée.
 */
final class ManageCustomIndicators
{
    /** Préfixe des codes attribués automatiquement : IND-C01, IND-C02… */
    public const CODE_PREFIX = 'IND-C';

    /** La colonne `position` est un entier sur 8 bits. */
    private const MAX_POSITION = 255;

    /**
     * @param  array{code?: string|null, label: string, definition?: string|null, formula?: string|null, source?: string|null, frequency: IndicatorFrequency, unit: string, target: float|int|string, better: string, value: float|int|string}  $data
     */
    public function create(array $data, User $by): Indicator
    {
        return DB::transaction(function () use ($data, $by): Indicator {
            $indicator = Indicator::query()->create([
                'code' => $this->code($data['code'] ?? null),
                'label' => trim($data['label']),
                'definition' => trim((string) ($data['definition'] ?? '')),
                'formula' => trim((string) ($data['formula'] ?? '')),
                'source' => trim((string) ($data['source'] ?? '')),
                'frequency' => $data['frequency'],
                'target' => $data['target'],
                'unit' => $data['unit'],
                'calc_key' => null,
                'better' => $data['better'] === Indicator::BETTER_DOWN ? Indicator::BETTER_DOWN : Indicator::BETTER_UP,
                'is_builtin' => false,
                'is_active' => true,
                'position' => min(self::MAX_POSITION, ((int) Indicator::query()->max('position')) + 1),
            ]);

            $this->record($indicator, $data['value']);
            Audit::log('indicator.create', $indicator, $indicator->code, $by);

            return $indicator;
        });
    }

    /**
     * Nouvelle valeur d'un indicateur saisi à la main, rattachée au mois en cours (une saisie par mois : la
     * suivante la remplace).
     */
    public function updateValue(Indicator $indicator, float|int|string $value, User $by): IndicatorValue
    {
        if (! $indicator->isManual()) {
            throw new DomainException('Seule la valeur d’un indicateur saisi à la main peut être modifiée.');
        }

        $row = $this->record($indicator, $value);
        Audit::log('indicator.value', $indicator, $indicator->code, $by);

        return $row;
    }

    /**
     * @throws DomainException indicateur intégré (la couche HTTP répond 422)
     */
    public function delete(Indicator $indicator, User $by): void
    {
        if ($indicator->is_builtin) {
            throw new DomainException('Les indicateurs intégrés ne peuvent pas être supprimés.');
        }

        DB::transaction(function () use ($indicator, $by): void {
            $indicator->values()->delete();
            $indicator->delete();
            Audit::log('indicator.delete', null, $indicator->code, $by);
        });
    }

    private function record(Indicator $indicator, float|int|string $value): IndicatorValue
    {
        $start = CarbonImmutable::now()->startOfMonth();

        return IndicatorValue::record($indicator, $start, $start->endOfMonth()->startOfDay(), null, $value, true);
    }

    /** Code saisi (majuscules) ou prochain IND-Cnn libre. */
    private function code(?string $requested): string
    {
        $requested = strtoupper(trim((string) $requested));

        if ($requested !== '') {
            return $requested;
        }

        $next = 1;

        foreach (Indicator::query()->where('code', 'like', self::CODE_PREFIX.'%')->pluck('code') as $code) {
            if (preg_match('/^'.self::CODE_PREFIX.'(\d+)$/', (string) $code, $matches) === 1) {
                $next = max($next, (int) $matches[1] + 1);
            }
        }

        return self::CODE_PREFIX.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }
}
