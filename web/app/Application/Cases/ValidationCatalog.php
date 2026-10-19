<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Domain\Cases\DuplicateCriteria;
use App\Domain\Cases\Plausibility;
use App\Domain\Cases\ValidationRuleKey;
use App\Models\ValidationRule;
use Illuminate\Support\Collection;

/**
 * Catalogue des règles de validation activables (P9-01, table `validation_rules`, Administration › Règles de
 * validation) lu une fois par instance : interrupteur, paramètres de plausibilité et champs de doublons. Une règle
 * absente du catalogue est **active** (on ne relâche jamais un contrôle par oubli, ValidationRule::isEnabled) ; une
 * règle verrouillée (`required`) l'est toujours. Pas de cache partagé : 12 lignes, lues au plus une fois par requête
 * et par service, jamais périmées après un réglage de l'administration.
 */
final class ValidationCatalog
{
    /** @var Collection<string, ValidationRule>|null */
    private ?Collection $rules = null;

    public function isActive(ValidationRuleKey|string $key): bool
    {
        $key = $key instanceof ValidationRuleKey ? $key : ValidationRuleKey::tryFrom($key);

        if ($key === null) {
            return true;
        }

        if ($key->isLocked()) {
            return true;
        }

        return $this->rules()->get($key->value)?->isActive() ?? true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function params(ValidationRuleKey $key): ?array
    {
        $params = $this->rules()->get($key->value)?->params;

        return is_array($params) ? $params : null;
    }

    /** Bornes de plausibilité (actives ou non : la règle `age_range` s'appuie sur les mêmes bornes). */
    public function plausibility(): Plausibility
    {
        return Plausibility::fromParams(
            $this->params(ValidationRuleKey::Plausibility),
            (int) config('xomiton.cases.old_case_warning_years', Plausibility::DEFAULT_MAX_PAST_YEARS),
        );
    }

    public function duplicateCriteria(): DuplicateCriteria
    {
        return DuplicateCriteria::fromParams($this->params(ValidationRuleKey::Duplicates));
    }

    /**
     * Règles actives, dans l'ordre de l'administration (verrouillées comprises) : `GET pair/validation-rules`.
     *
     * @return Collection<int, ValidationRule>
     */
    public function active(): Collection
    {
        return $this->rules()->filter(static fn (ValidationRule $rule): bool => $rule->isActive())->values();
    }

    /** Dernière modification du catalogue (version du cache mobile), null s'il est vide. */
    public function lastUpdatedAt(): ?string
    {
        $latest = $this->rules()->max(static fn (ValidationRule $rule): int => (int) $rule->updated_at?->getTimestamp());

        return is_int($latest) && $latest > 0 ? now()->setTimestamp($latest)->toIso8601String() : null;
    }

    /** Relit la table (après un réglage dans la même requête). */
    public function refresh(): void
    {
        $this->rules = null;
    }

    /**
     * @return Collection<string, ValidationRule>
     */
    private function rules(): Collection
    {
        return $this->rules ??= ValidationRule::query()->ordered()->get()->keyBy('key');
    }
}
