<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Application\Push\PushToPeers;
use App\Domain\Cases\DuplicateCriteria;
use App\Domain\Cases\ValidationRuleKey;
use App\Models\User;
use App\Models\ValidationRule;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Réglages du catalogue des règles de validation (P11-05, Administration › Règles de validation ; prototype
 * admin/validation.php l.22-48) : interrupteur de chaque règle, bornes de plausibilité, champs de doublons suspects. Lus par
 * ValidationCatalog (formulaires, API `pair/`, `sync/push`, règles `email`, `phone_bj`, `positive_int`) : un réglage prend
 * effet à la requête suivante, et `updated_at` renouvelle la version servie aux clients mobiles (push silencieux `rules` aux pair·es, P15-20). Autorisation :
 * `ValidationRulePolicy::update` (`validation_rules.manage`). Les règles **verrouillées** (`required`, `password_strong`) restent actives
 * quoi qu'on demande : la demande est ignorée et rapportée. Journal d'audit « validation.rules_updated »,
 * « validation.plausibility_updated », « validation.duplicates_updated » (clés et bornes, jamais de donnée de dossier).
 */
final class ManageValidationRules
{
    /** Bornes de saisie de la plausibilité (âges en années, ancienneté maximale d'une date en années). */
    public const AGE_MAX_LIMIT = 120;

    public const PAST_YEARS_LIMIT = 50;

    /**
     * Active ou désactive des règles : `$active` associe la clé de chaque règle de l'écran à son nouvel état.
     * Renvoie les clés effectivement changées et les clés verrouillées dont on a demandé la désactivation.
     *
     * @param  array<string, bool>  $active
     * @return array{changed: list<string>, locked: list<string>}
     */
    public function setActivation(User $by, array $active): array
    {
        Gate::forUser($by)->authorize('update', new ValidationRule);

        $changed = [];
        $locked = [];

        DB::transaction(function () use ($active, &$changed, &$locked): void {
            foreach (ValidationRule::query()->ordered()->get() as $rule) {
                if (! array_key_exists($rule->key, $active)) {
                    continue;
                }

                $wanted = (bool) $active[$rule->key];
                $key = ValidationRuleKey::tryFrom($rule->key);

                if (($rule->is_locked || $key?->isLocked()) && ! $wanted) {
                    $locked[] = $rule->key;
                    $wanted = true;
                }

                if ($rule->is_active !== $wanted) {
                    $rule->is_active = $wanted;
                    $rule->save();
                    $changed[] = $rule->key;
                }
            }
        });

        if ($changed !== []) {
            $this->notifyPeers();
            Audit::log('validation.rules_updated', null, sprintf('règles=%s', implode(',', array_map(
                fn (string $key): string => $key.'='.($active[$key] ? 'on' : 'off'),
                $changed,
            ))), $by);
        }

        return ['changed' => $changed, 'locked' => $locked];
    }

    /**
     * Bornes de plausibilité (règle `plausibility`) : tranche d'âge acceptée (`age_min` < `age_max` ≤ 120), ancienneté
     * maximale d'une date (1 à 50 ans, avertissement RG-07). Les bornes GPS et la liste des formulaires sont conservées.
     */
    public function savePlausibility(User $by, int $ageMin, int $ageMax, int $maxPastYears, bool $active): ValidationRule
    {
        $rule = $this->rule(ValidationRuleKey::Plausibility->value);
        Gate::forUser($by)->authorize('update', $rule);

        $rule->params = array_merge($rule->params ?? [], ['age_min' => $ageMin, 'age_max' => $ageMax, 'max_past_years' => $maxPastYears]);
        $rule->is_active = $active;
        $rule->save();

        $this->notifyPeers();
        Audit::log('validation.plausibility_updated', $rule, sprintf('âge=%d-%d ; ancienneté=%d ans ; active=%s', $ageMin, $ageMax, $maxPastYears, $active ? 'oui' : 'non'), $by);

        return $rule;
    }

    /**
     * Détection des doublons suspects (règle `duplicates`) : au moins deux champs parmi ceux du catalogue
     * (DuplicateCriteria::COLUMNS), dans l'ordre du catalogue ; un champ inconnu est ignoré.
     *
     * @param  list<string>  $fields
     */
    public function saveDuplicates(User $by, bool $active, array $fields): ValidationRule
    {
        $rule = $this->rule(ValidationRuleKey::Duplicates->value);
        Gate::forUser($by)->authorize('update', $rule);

        $fields = array_values(array_intersect(array_keys(DuplicateCriteria::COLUMNS), $fields));
        $rule->params = array_merge($rule->params ?? [], ['fields' => $fields]);
        $rule->is_active = $active;
        $rule->save();

        $this->notifyPeers();
        Audit::log('validation.duplicates_updated', $rule, sprintf('champs=%s ; active=%s', implode('+', $fields), $active ? 'oui' : 'non'), $by);

        return $rule;
    }

    /** Réveille les pair·es (push silencieux `rules`, P15-20) : ils relisent `pair/validation-rules` à leur prochain contact. */
    private function notifyPeers(): void
    {
        app(PushToPeers::class)->rulesChanged();
    }

    private function rule(string $key): ValidationRule
    {
        return ValidationRule::query()->withKey($key)->firstOrFail();
    }
}
