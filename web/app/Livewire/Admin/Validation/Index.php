<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Validation;

use App\Application\Cases\ValidationCatalog;
use App\Application\Validation\DataQualityReport;
use App\Application\Validation\ManageValidationRules;
use App\Application\Validation\RuleSandbox;
use App\Domain\Cases\DuplicateCriteria;
use App\Domain\Cases\Plausibility;
use App\Domain\Cases\ValidationRuleKey;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Models\User;
use App\Models\ValidationRule;
use App\Rules\PositiveInteger;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Règles de validation `/admin/validation` (P11-05, prototype admin/validation.php) : KPI, catalogue (activer /
 * désactiver, exemples valide et invalide, formulaires concernés), bac à sable (la valeur est essayée avec le code même des
 * formulaires, RuleSandbox), détection des doublons suspects, règles de plausibilité (tranche d'âge, ancienneté d'une
 * date) et rapport de qualité des données (cache 60 s). Réservé à `validation_rules.manage` (ValidationRulePolicy).
 *
 * Les règles **verrouillées** (`required` et `password_strong`) sont toujours actives : l'interrupteur est grisé et une
 * demande forgée est ignorée. Le catalogue est celui que lit ValidationCatalog : formulaires de collecte et de suivi,
 * API `pair/`, `sync/push`, inscription (`email`, `phone_bj`, `password_strong`), annuaire, indicateurs (`positive_int`).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;

    public const REPORT_CACHE_KEY = 'admin:validation:report';

    /**
     * Interrupteur de chaque règle du catalogue (clé => actif).
     *
     * @var array<string, bool>
     */
    public array $active = [];

    /**
     * Bornes de plausibilité en cours de saisie.
     *
     * @var array{age_min: int|string, age_max: int|string, max_past_years: int|string, is_active: bool}
     */
    public array $plausibility = ['age_min' => 10, 'age_max' => 99, 'max_past_years' => 5, 'is_active' => true];

    /**
     * Détection des doublons en cours de saisie.
     *
     * @var array{is_active: bool, fields: list<string>}
     */
    public array $duplicates = ['is_active' => true, 'fields' => []];

    public string $sandboxRule = 'phone_bj';

    public string $sandboxValue = '';

    public function boot(): void
    {
        $this->authorize('viewAny', ValidationRule::class);
    }

    public function mount(): void
    {
        $this->load();
    }

    // --- Actions de la vue ---------------------------------------------------------------------------------------------

    public function saveRules(ManageValidationRules $manage): void
    {
        $result = $manage->setActivation($this->user(), $this->active);
        $this->afterWrite();

        foreach ($result['locked'] as $key) {
            $this->toast((string) __('admin_data.validation.toasts.locked', ['rule' => $this->label($key)]), 'warning');
        }

        $this->toast((string) __('admin_data.validation.toasts.rules_saved', ['active' => $this->activeCount(), 'total' => count($this->catalogRules())]));
    }

    public function savePlausibility(ManageValidationRules $manage): void
    {
        $data = $this->validateForm('plausibility', $this->plausibility, [
            'age_min' => ['required', 'integer', 'min:0', 'max:'.ManageValidationRules::AGE_MAX_LIMIT, new PositiveInteger],
            'age_max' => ['required', 'integer', 'min:1', 'max:'.ManageValidationRules::AGE_MAX_LIMIT, 'gt:age_min', new PositiveInteger],
            'max_past_years' => ['required', 'integer', 'min:1', 'max:'.ManageValidationRules::PAST_YEARS_LIMIT, new PositiveInteger],
            'is_active' => ['boolean'],
        ], (array) __('admin_data.validation.plausibility.fields'));

        $bounds = new Plausibility((int) $data['age_min'], (int) $data['age_max'], (int) $data['max_past_years']);

        if ($bounds->acceptedAgeRanges() === []) {
            $this->addError('plausibility.age_max', (string) __('admin_data.validation.plausibility.no_range'));

            return;
        }

        $manage->savePlausibility($this->user(), (int) $data['age_min'], (int) $data['age_max'], (int) $data['max_past_years'], (bool) $data['is_active']);
        $this->afterWrite();
        $this->toast((string) __('admin_data.validation.toasts.plausibility_saved'));
    }

    public function saveDuplicates(ManageValidationRules $manage): void
    {
        $fields = array_values(array_intersect(array_keys(DuplicateCriteria::COLUMNS), array_map('strval', $this->duplicates['fields'] ?? [])));

        if (count($fields) < 2) {
            $this->addError('duplicates.fields', (string) __('admin_data.validation.duplicates.min_fields'));

            return;
        }

        $this->resetErrorBag('duplicates.fields');
        $manage->saveDuplicates($this->user(), (bool) ($this->duplicates['is_active'] ?? true), $fields);
        $this->afterWrite();
        $this->toast((string) __('admin_data.validation.toasts.duplicates_saved'));
    }

    /** « Tester » d'une ligne du catalogue : sélectionne la règle et propose son exemple invalide (sinon valide). */
    public function tryRule(string $key): void
    {
        $rule = $this->catalogRules()->firstWhere('key', $key);

        if ($rule === null || ValidationRuleKey::tryFrom($key) === null || in_array(ValidationRuleKey::from($key), RuleSandbox::UNSUPPORTED, true)) {
            return;
        }

        $this->sandboxRule = $key;
        $this->sandboxValue = (string) ($rule->invalid_example ?? $rule->valid_example ?? '');
    }

    // --- Données de la vue ---------------------------------------------------------------------------------------------

    /**
     * Règles du catalogue affichées dans le tableau (hors plausibilité et doublons, qui ont leur propre carte).
     *
     * @return Collection<int, ValidationRule>
     */
    public function catalogRules(): Collection
    {
        return ValidationRule::query()->ordered()->get()
            ->reject(static fn (ValidationRule $rule): bool => in_array($rule->key, [ValidationRuleKey::Plausibility->value, ValidationRuleKey::Duplicates->value], true))
            ->values();
    }

    /**
     * Rapport de qualité (cache 60 s, oublié à chaque réglage).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function report(): array
    {
        $viewer = $this->user();

        /** @var array<string, mixed> */
        return Cache::remember(self::REPORT_CACHE_KEY.':'.$viewer->getKey(), 60, static fn (): array => app(DataQualityReport::class)($viewer));
    }

    public function render(): View
    {
        $rules = $this->catalogRules();
        $sandbox = app(RuleSandbox::class);
        $report = $this->report;

        return view('livewire.admin.validation.index', [
            'rules' => $rules,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'activeCount' => $this->activeCount(),
            'sandboxResult' => $sandbox->check($this->sandboxRule, $this->sandboxValue),
            'sandboxRules' => RuleSandbox::testable(),
            'locked' => ValidationRuleKey::locked(),
            'availableFields' => array_keys(DuplicateCriteria::COLUMNS),
            'report' => $report,
            'plausibilityEffect' => app(ValidationCatalog::class)->plausibility(),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_data.validation.title'),
            'adminPage' => 'validation',
        ]);
    }

    // --- Interne ---------------------------------------------------------------------------------------------------------

    private function load(): void
    {
        $this->active = [];

        foreach (ValidationRule::query()->ordered()->get() as $rule) {
            $this->active[$rule->key] = $rule->isActive();
        }

        $catalog = new ValidationCatalog;
        $bounds = $catalog->plausibility();
        $this->plausibility = [
            'age_min' => $bounds->ageMin,
            'age_max' => $bounds->ageMax,
            'max_past_years' => $bounds->maxPastYears,
            'is_active' => $catalog->isActive(ValidationRuleKey::Plausibility),
        ];
        $this->duplicates = [
            'is_active' => $catalog->isActive(ValidationRuleKey::Duplicates),
            'fields' => $catalog->duplicateCriteria()->fields,
        ];
    }

    private function afterWrite(): void
    {
        Cache::forget(self::REPORT_CACHE_KEY.':'.$this->user()->getKey());
        unset($this->report);
        $this->load();
    }

    private function activeCount(): int
    {
        return ValidationRule::query()->get()->filter(static fn (ValidationRule $rule): bool => $rule->isActive())->count();
    }

    private function label(string $key): string
    {
        return (string) ($this->catalogRules()->firstWhere('key', $key)?->label ?? $key);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
