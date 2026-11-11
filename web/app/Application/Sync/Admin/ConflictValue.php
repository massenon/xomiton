<?php

declare(strict_types=1);

namespace App\Application\Sync\Admin;

use App\Enums\CaseStatus;
use App\Enums\Sex;
use App\Models\Commune;
use App\Models\Population;
use App\Models\ViolenceType;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Valeur d'un conflit de synchronisation lisible par l'administration (P13-01) : identifiants de référentiels remplacés
 * par leur libellé, statut et sexe par leur libellé, date en français, texte tel quel. Aucune écriture.
 */
final class ConflictValue
{
    /** @var array<string, array<string, string>> */
    private array $names = [];

    public function present(string $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return (string) __('admin_system.sync.value_empty');
        }

        try {
            return match ($field) {
                'commune_id' => $this->name(Commune::class, $value),
                'population_id' => $this->name(Population::class, $value, 'label'),
                'violence_type_id' => $this->name(ViolenceType::class, $value, 'label'),
                'status' => CaseStatus::tryFrom($value)?->label() ?? $value,
                'sex' => Sex::tryFrom($value)?->label() ?? $value,
                'occurred_on' => Carbon::parse($value)->locale('fr')->isoFormat('LL'),
                default => $value,
            };
        } catch (Throwable) {
            return $value;
        }
    }

    /** @param  class-string<\Illuminate\Database\Eloquent\Model>  $model */
    private function name(string $model, string $id, string $column = 'name'): string
    {
        $this->names[$model][$id] ??= (string) ($model::query()->whereKey($id)->value($column) ?? $id);

        return $this->names[$model][$id];
    }
}
