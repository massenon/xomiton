<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Indicators;

use App\Application\Shared\Referentials;
use App\Application\Steering\ContentMetrics;
use App\Application\Steering\IndicatorBoard;
use App\Application\Steering\IndicatorRow;
use App\Application\Steering\ManageCustomIndicators;
use App\Application\Steering\Period;
use App\Enums\IndicatorFrequency;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Indicator;
use App\Models\User;
use App\Models\Zone;
use App\Support\Audit;
use App\Support\CsvExport;
use App\Support\LocaleFormatter;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Indicateurs `/admin/indicateurs` (P10-06, prototype admin/indicateurs.php) : IND-01 à IND-16 calculés en direct par
 * IndicatorCalculator (IND-04 : premier suivi ≤ création + 72 h ; IND-10 : alerte traitée ou close ≤ réception + 24 h),
 * valeur, cible, avancement, tendance sur les six derniers instantanés mensuels (`indicators:snapshot`), filtres de
 * période et de département, effectifs de 1 à 4 masqués, graphique à la demande (Chart.js chargé au clic), indicateurs
 * personnalisés à valeur manuelle (création, nouvelle valeur, suppression des seuls indicateurs personnalisés), export
 * CSV des indicateurs filtrés et tableau « Contenu » sans traceur tiers. Lecture : `indicators.view` ; saisie et
 * suppression : `indicators.manage`.
 */
final class Index extends Component
{
    use InteractsWithUi;

    public const PRESETS = ['mois', 'prec', 'trim', 'annee', 'perso'];

    private const CACHE_SECONDS = 30;

    private const CACHE_VERSION_KEY = 'admin-indicators:version';

    #[SafeUrl(as: 'p', except: 'prec')]
    public string $preset = 'prec';

    #[SafeUrl(as: 'du', except: '')]
    public string $from = '';

    #[SafeUrl(as: 'au', except: '')]
    public string $until = '';

    #[SafeUrl(as: 'zone', except: '')]
    public string $zone = '';

    #[SafeUrl(as: 'freq', except: '')]
    public string $frequency = '';

    // --- Modale « Nouvel indicateur personnalisé » ---
    public string $code = '';

    public string $label = '';

    public string $definition = '';

    public string $formula = '';

    public string $source = '';

    public string $newFrequency = 'mensuelle';

    public string $unit = 'nb';

    public string $target = '';

    public string $better = 'up';

    public string $value = '';

    // --- Modale « Nouvelle valeur » ---
    public int $editId = 0;

    public string $editValue = '';

    public function mount(): void
    {
        $this->authorize('indicators.view');
    }

    /** « Réinitialiser » : période précédente, tous les départements, toutes les fréquences. */
    public function resetFilters(): void
    {
        $this->reset(['preset', 'from', 'until', 'zone', 'frequency']);
        $this->resetErrorBag();
    }

    public function updatedPreset(): void
    {
        if (! in_array($this->preset, self::PRESETS, true)) {
            $this->preset = 'prec';
        }

        if ($this->preset !== 'perso') {
            $this->from = '';
            $this->until = '';
        }
    }

    public function updatedFrom(): void
    {
        $this->preset = 'perso';
    }

    public function updatedUntil(): void
    {
        $this->preset = 'perso';
    }

    /** Période affichée ; en cas de dates invalides, le mois précédent (l'erreur est affichée sous les champs). */
    #[Computed]
    public function period(): Period
    {
        return $this->resolvePeriod() ?? Period::previousMonth();
    }

    #[Computed]
    public function periodError(): ?string
    {
        if ($this->preset !== 'perso' || ($this->from === '' && $this->until === '')) {
            return null;
        }

        return $this->resolvePeriod() === null ? (string) __('steering.indicators.filters.period_invalid') : null;
    }

    /** @return list<IndicatorRow> */
    #[Computed]
    public function rows(): array
    {
        $zone = $this->selectedZone();
        $frequency = IndicatorFrequency::tryFrom($this->frequency);

        // Cache de 30 s par (période, département, fréquence) : sur un mutualisé, chaque aller-retour Livewire (blur d'un champ
        // de modale, filtre) ne recalcule pas les seize indicateurs (IND-04 et IND-10 parcourent les lignes en PHP).
        return Cache::remember(
            $this->cacheKey('rows', $zone?->getKey(), $frequency?->value),
            self::CACHE_SECONDS,
            fn (): array => app(IndicatorBoard::class)->rows($this->period, $zone, $frequency),
        );
    }

    /** Tableau « Contenu » de la période, mis en cache comme les indicateurs. */
    #[Computed]
    public function content(): array
    {
        return Cache::remember($this->cacheKey('content'), self::CACHE_SECONDS, fn (): array => app(ContentMetrics::class)->rows($this->period));
    }

    /** Invalide les lignes en cache (création, nouvelle valeur ou suppression d'un indicateur personnalisé). */
    private function forgetCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, (int) Cache::get(self::CACHE_VERSION_KEY, 1) + 1);
        unset($this->rows, $this->content);
    }

    private function cacheKey(string $name, int|string|null ...$parts): string
    {
        $period = $this->period->start->toDateString().'_'.$this->period->end->toDateString();

        return sprintf('admin-indicators:%d:%s:%s:%s', (int) Cache::get(self::CACHE_VERSION_KEY, 1), $name, $period, implode('_', array_map('strval', $parts)));
    }

    /**
     * Création d'un indicateur personnalisé (valeur saisie à la main).
     */
    public function create(ManageCustomIndicators $indicators): void
    {
        $this->authorize('indicators.manage');
        $this->code = strtoupper(trim($this->code));
        $this->unit = in_array($this->unit, [Indicator::UNIT_COUNT, Indicator::UNIT_PERCENT], true) ? $this->unit : Indicator::UNIT_COUNT;

        $data = $this->validate([
            'code' => ['nullable', 'regex:/^[A-Z0-9-]{2,10}$/', Rule::unique('indicators', 'code')],
            'label' => ['required', 'string', 'max:160'],
            'definition' => ['nullable', 'string', 'max:500'],
            'formula' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:80'],
            'newFrequency' => ['required', Rule::enum(IndicatorFrequency::class)],
            'unit' => ['required', Rule::in([Indicator::UNIT_COUNT, Indicator::UNIT_PERCENT])],
            'target' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'better' => ['required', Rule::in([Indicator::BETTER_UP, Indicator::BETTER_DOWN])],
            'value' => ['required', 'numeric', 'min:0', 'max:'.($this->unit === Indicator::UNIT_PERCENT ? 100 : 99999999)],
        ], attributes: $this->fieldNames());

        $indicator = $indicators->create([
            'code' => $data['code'] ?? null,
            'label' => $data['label'],
            'definition' => $data['definition'] ?? null,
            'formula' => $data['formula'] ?? null,
            'source' => $data['source'] ?? null,
            'frequency' => IndicatorFrequency::from($data['newFrequency']),
            'unit' => $data['unit'],
            'target' => $data['target'],
            'better' => $data['better'],
            'value' => $data['value'],
        ], $this->user());

        $this->reset(['code', 'label', 'definition', 'formula', 'source', 'newFrequency', 'unit', 'target', 'better', 'value']);
        $this->resetErrorBag();
        $this->forgetCache();
        $this->closeModal('xmIndicatorModal');
        $this->toast((string) __('steering.indicators.toasts.created', ['label' => $indicator->label]));
    }

    public function editValue(int $id): void
    {
        $this->authorize('indicators.manage');
        $indicator = Indicator::query()->manual()->whereKey($id)->first();

        if ($indicator === null) {
            $this->toast((string) __('steering.indicators.errors.not_found'), 'error');

            return;
        }

        $this->editId = $id;
        $this->editValue = '';
        $this->resetErrorBag('editValue');
        $this->openModal('xmIndicatorValueModal');
    }

    public function saveValue(ManageCustomIndicators $indicators): void
    {
        $this->authorize('indicators.manage');
        $indicator = Indicator::query()->manual()->whereKey($this->editId)->first();

        if ($indicator === null) {
            $this->toast((string) __('steering.indicators.errors.not_found'), 'error');

            return;
        }

        $data = $this->validate([
            'editValue' => ['required', 'numeric', 'min:0', 'max:'.($indicator->isPercentage() ? 100 : 99999999)],
        ], attributes: ['editValue' => (string) __('steering.indicators.fields.value')]);

        $indicators->updateValue($indicator, $data['editValue'], $this->user());
        $this->forgetCache();
        $this->editId = 0;
        $this->editValue = '';
        $this->closeModal('xmIndicatorValueModal');
        $this->toast((string) __('steering.indicators.toasts.value_saved', ['label' => $indicator->label]));
    }

    /**
     * Suppression d'un indicateur personnalisé ; un indicateur intégré répond 422 (requête forgée : aucun bouton ne
     * l'offre).
     */
    public function delete(int $id, ManageCustomIndicators $indicators): void
    {
        $this->authorize('indicators.manage');
        $indicator = Indicator::query()->find($id);

        if ($indicator === null) {
            $this->toast((string) __('steering.indicators.errors.not_found'), 'error');

            return;
        }

        try {
            $indicators->delete($indicator, $this->user());
        } catch (DomainException) {
            abort(422, (string) __('steering.indicators.errors.builtin'));
        }

        $this->forgetCache();
        $this->toast((string) __('steering.indicators.toasts.deleted', ['label' => $indicator->label]));
    }

    /**
     * Export CSV des indicateurs filtrés (valeurs masquées en clair : « données masquées »), journalisé.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('indicators.view');
        $format = LocaleFormatter::make();
        $rows = [];

        foreach ($this->rows as $row) {
            $indicator = $row->indicator;
            $rows[] = [
                'code' => $indicator->code,
                'label' => $indicator->label,
                'formula' => $indicator->formula,
                'source' => $indicator->source,
                'frequency' => $indicator->frequency->label(),
                'target' => $format->number((float) $indicator->target, 2),
                'current' => $row->masked ? (string) __('admin.kpi.masked') : ($row->value === null ? '' : $format->number($row->value, 2)),
                'unit' => $indicator->unit === Indicator::UNIT_PERCENT ? '%' : (string) __('steering.indicators.unit_count'),
                'progress' => $row->progress === null ? '' : $format->number($row->progress, 0),
            ];
        }

        Audit::log('export.csv', null, trans_choice('admin.export.audit', count($rows), ['screen' => 'indicateurs', 'count' => count($rows)]), $this->user());

        return CsvExport::download(CsvExport::filename('indicateurs'), [
            'code' => (string) __('steering.indicators.columns.code'),
            'label' => (string) __('steering.indicators.columns.label'),
            'formula' => (string) __('steering.indicators.columns.formula'),
            'source' => (string) __('steering.indicators.columns.source'),
            'frequency' => (string) __('steering.indicators.columns.frequency'),
            'target' => (string) __('steering.indicators.columns.target'),
            'current' => (string) __('steering.indicators.columns.value'),
            'unit' => (string) __('steering.indicators.columns.unit'),
            'progress' => (string) __('steering.indicators.columns.progress'),
        ], $rows);
    }

    public function render(): View
    {
        $rows = $this->rows;
        $canManage = (bool) $this->user()->can('indicators.manage');

        return view('livewire.admin.indicators.index', [
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'items' => $rows,
            'summary' => IndicatorBoard::summary($rows),
            'content' => $this->content,
            'zones' => app(Referentials::class)->zones(),
            'frequencies' => IndicatorFrequency::cases(),
            'canManage' => $canManage,
            'editing' => $this->editId > 0 ? Indicator::query()->find($this->editId) : null,
            'filtered' => $this->preset !== 'prec' || $this->zone !== '' || $this->frequency !== '',
        ])->layout('components.layouts.admin', [
            'title' => __('steering.indicators.title'),
            'adminPage' => 'indicateurs',
        ]);
    }

    private function resolvePeriod(): ?Period
    {
        $now = CarbonImmutable::now(config('xomiton.display_timezone', 'UTC'))->startOfDay();

        return match ($this->preset) {
            'mois' => new Period($now->startOfMonth(), $now->endOfMonth()->startOfDay()),
            'trim' => new Period($now->startOfQuarter(), $now->endOfQuarter()->startOfDay()),
            'annee' => new Period($now->startOfYear(), $now->endOfYear()->startOfDay()),
            'perso' => $this->customPeriod(),
            default => Period::previousMonth($now),
        };
    }

    private function customPeriod(): ?Period
    {
        $from = $this->parseDate($this->from);
        $until = $this->parseDate($this->until);

        if ($from === null || $until === null || $until->lessThan($from) || $from->diffInYears($until) >= 5) {
            return null;
        }

        return new Period($from, $until);
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            return null;
        }

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function selectedZone(): ?Zone
    {
        return ctype_digit($this->zone) && $this->zone !== '' ? Zone::query()->active()->find((int) $this->zone) : null;
    }

    /**
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return [
            'code' => (string) __('steering.indicators.fields.code'),
            'label' => (string) __('steering.indicators.fields.label'),
            'definition' => (string) __('steering.indicators.fields.definition'),
            'formula' => (string) __('steering.indicators.fields.formula'),
            'source' => (string) __('steering.indicators.fields.source'),
            'newFrequency' => (string) __('steering.indicators.fields.frequency'),
            'unit' => (string) __('steering.indicators.fields.unit'),
            'target' => (string) __('steering.indicators.fields.target'),
            'better' => (string) __('steering.indicators.fields.better'),
            'value' => (string) __('steering.indicators.fields.value'),
        ];
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
