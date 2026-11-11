<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Application\Cases\Results\CaseWarning;
use App\Domain\Cases\ValidationRuleKey;
use App\Models\CaseRecord;
use App\Models\User;
use App\Policies\Scopes\CaseRecordScope;
use App\Rules\NotFutureDate;
use BackedEnum;
use Carbon\CarbonImmutable;

/**
 * Contrôles de qualité non bloquants d'un dossier enregistré (P9-01) :
 *
 *  - plausibilité (règle `plausibility`) : date des faits de plus de `max_past_years` ans (RG-07, 5 par défaut) ;
 *  - doublons suspects (règle `duplicates`) : autres dossiers partageant les champs du catalogue (date, commune,
 *    population, tranche d'âge par défaut), **parmi ceux que le compte voit** (`CaseRecord::visibleTo`) : un pair ne
 *    découvre jamais l'existence du dossier d'un autre ; le rapprochement entre pair·es relève du rapport de qualité
 *    de l'administration (P11). Dossiers anonymisés exclus, au plus `MAX_DUPLICATES` codes.
 */
final class CaseQuality
{
    public const MAX_DUPLICATES = 5;

    public function __construct(private readonly ValidationCatalog $catalog) {}

    /**
     * @return list<CaseWarning>
     */
    public function warnings(CaseRecord $case, User $viewer): array
    {
        $warnings = [];

        if ($this->catalog->isActive(ValidationRuleKey::Plausibility) && $case->occurred_on !== null) {
            $plausibility = $this->catalog->plausibility();

            if ($plausibility->isTooOld(CarbonImmutable::instance($case->occurred_on), NotFutureDate::today())) {
                $warnings[] = new CaseWarning(
                    CaseWarning::DATE_OLD,
                    (string) __('cases.warnings.date_old', ['years' => $plausibility->maxPastYears]),
                );
            }
        }

        if ($this->catalog->isActive(ValidationRuleKey::Duplicates)) {
            $codes = $this->duplicates($case, $viewer);

            if ($codes !== []) {
                $warnings[] = new CaseWarning(
                    CaseWarning::DUPLICATE_SUSPECTED,
                    (string) trans_choice('cases.warnings.duplicate_suspected', count($codes), ['codes' => implode(', ', $codes)]),
                    $codes,
                );
            }
        }

        return $warnings;
    }

    /**
     * Codes des dossiers visibles qui partagent les champs comparés (aucun si l'un des champs est vide).
     *
     * @return list<string>
     */
    public function duplicates(CaseRecord $case, User $viewer): array
    {
        $criteria = $this->catalog->duplicateCriteria();
        $attributes = [];

        foreach ($criteria->columns() as $column) {
            $value = $column === 'occurred_on' ? $case->occurred_on?->format('Y-m-d') : $case->getAttribute($column);
            $attributes[$column] = $value instanceof BackedEnum ? $value->value : $value;
        }

        $values = $criteria->valuesFrom($attributes);

        if ($values === null) {
            return [];
        }

        $query = CaseRecordScope::visibleTo($viewer)
            ->whereKeyNot($case->getKey())
            ->whereNull('anonymized_at');

        foreach ($values as $column => $value) {
            $column === 'occurred_on' ? $query->whereDate($column, (string) $value) : $query->where($column, $value);
        }

        /** @var list<string> $codes */
        $codes = $query->orderBy('code')->limit(self::MAX_DUPLICATES)->pluck('code')->map(static fn (mixed $code): string => (string) $code)->all();

        return $codes;
    }
}
