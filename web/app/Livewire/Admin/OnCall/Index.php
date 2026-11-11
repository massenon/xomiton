<?php

declare(strict_types=1);

namespace App\Livewire\Admin\OnCall;

use App\Application\Admin\OnCall\DeleteOnCallShift;
use App\Application\Admin\OnCall\OnCallShiftRules;
use App\Application\Admin\OnCall\SaveOnCallShift;
use App\Application\Alerts\OnCallRoster;
use App\Application\Shared\Referentials;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Models\OnCallShift;
use App\Models\User;
use App\Support\LocaleFormatter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Planning de garde `/admin/garde` (P1-17) : qui est « admin de garde » ou point focal remplaçant, donc qui reçoit les
 * SOS et l'escalade (OnCallRoster). Liste par semaine ou par mois, gardes en cours et prochaine, création, modification
 * et suppression d'une plage (confirmation et avertissement pour une plage en cours). Les heures se saisissent et
 * s'affichent dans `xomiton.display_timezone` et sont enregistrées en UTC. Règles : OnCallShiftRules.
 *
 * Réservé à `on_call.manage` (OnCallShiftPolicy), revérifié à chaque action Livewire (boot) puis par chaque cas d'usage.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;

    public const FORM_MODAL = 'xmOnCallForm';

    public const DELETE_MODAL = 'xmOnCallDelete';

    public const VIEWS = ['semaine', 'mois'];

    /** Vue de la liste : semaine ou mois. */
    #[SafeUrl(as: 'vue', except: 'semaine')]
    public string $view = 'semaine';

    /** Jour de repère de la période (AAAA-MM-JJ, fuseau d'affichage) ; vide : aujourd'hui. */
    #[SafeUrl(as: 'date', except: '')]
    public string $day = '';

    /**
     * Formulaire de la modale : kind, user_id, zone_id, starts_at, ends_at (AAAA-MM-JJThh:mm, fuseau d'affichage), notes.
     *
     * @var array<string, string>
     */
    public array $form = [];

    /** Plage modifiée (0 : création) ; verrouillé. */
    #[Locked]
    public int $editId = 0;

    /** Plage dont la suppression attend confirmation ; verrouillé. */
    #[Locked]
    public int $deleteId = 0;

    public function boot(): void
    {
        $this->authorize('viewAny', OnCallShift::class);
        abort_unless($this->user()->can('on_call.manage'), 403);
    }

    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    // --- Période -----------------------------------------------------------------------------------------------------

    public function setView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : 'semaine';
    }

    public function previous(): void
    {
        $this->day = $this->shiftAnchor(-1)->format('Y-m-d');
    }

    public function next(): void
    {
        $this->day = $this->shiftAnchor(1)->format('Y-m-d');
    }

    public function today(): void
    {
        $this->day = '';
    }

    // --- Actions de la vue -------------------------------------------------------------------------------------------

    public function openCreate(): void
    {
        $this->authorize('create', OnCallShift::class);
        $this->resetErrorBag();
        $this->editId = 0;
        $this->form = $this->blankForm();
        $this->openModal(self::FORM_MODAL);
    }

    public function openEdit(int $id): void
    {
        $shift = OnCallShift::query()->find($id);

        if ($shift === null) {
            $this->toast((string) __('admin_oncall.errors.not_found'), 'error');

            return;
        }

        $this->authorize('update', $shift);

        if ($shift->ends_at->lessThanOrEqualTo(now())) {
            $this->toast((string) __('admin_oncall.errors.finished'), 'warning');

            return;
        }

        $this->resetErrorBag();
        $this->editId = (int) $shift->getKey();
        $this->form = [
            'kind' => $shift->kind,
            'user_id' => (string) $shift->user_id,
            'zone_id' => $shift->zone_id === null ? '' : (string) $shift->zone_id,
            'starts_at' => $this->toInput($shift->starts_at),
            'ends_at' => $this->toInput($shift->ends_at),
            'notes' => (string) ($shift->notes ?? ''),
        ];
        $this->openModal(self::FORM_MODAL);
    }

    /** Changer de type : la zone n'a de sens que pour un point focal, et la personne doit rester éligible. */
    public function updatedFormKind(OnCallShiftRules $rules): void
    {
        $kind = $this->form['kind'] ?? '';

        if ($kind !== OnCallShift::KIND_FOCAL) {
            $this->form['zone_id'] = '';
        }

        $eligible = $rules->eligibleUsers(in_array($kind, OnCallShift::KINDS, true) ? $kind : OnCallShift::KIND_ADMIN)
            ->contains(fn (User $user): bool => (string) $user->getKey() === ($this->form['user_id'] ?? ''));

        if (! $eligible) {
            $this->form['user_id'] = '';
        }
    }

    public function save(SaveOnCallShift $save, OnCallShiftRules $rules): void
    {
        $user = $this->user();
        $shift = $this->editId === 0 ? null : OnCallShift::query()->find($this->editId);

        if ($this->editId !== 0 && $shift === null) {
            $this->toast((string) __('admin_oncall.errors.not_found'), 'error');

            return;
        }

        $data = [];

        foreach ($this->form as $key => $value) {
            $data[$key] = trim((string) $value) === '' ? null : trim((string) $value);
        }

        $validated = $this->validateForm('form', $data, $rules->rules(), $this->fieldNames());
        $start = $this->fromInput((string) $validated['starts_at']);
        $end = $this->fromInput((string) $validated['ends_at']);

        if ($start === null || $end === null) {
            $this->addError('form.starts_at', (string) __('admin_oncall.errors.invalid_date'));

            return;
        }

        $payload = [
            'kind' => (string) $validated['kind'],
            'user_id' => (int) $validated['user_id'],
            'zone_id' => $validated['kind'] === OnCallShift::KIND_FOCAL && ! empty($validated['zone_id']) ? (int) $validated['zone_id'] : null,
            'starts_at' => $start,
            'ends_at' => $end,
            'notes' => isset($validated['notes']) ? (string) $validated['notes'] : null,
        ];
        $problems = $rules->crossChecks($payload, $shift);

        if ($problems !== []) {
            foreach ($problems as $field => $message) {
                $this->addError('form.'.$field, $message);
            }

            return;
        }

        $save($user, $payload, $shift);

        $this->closeModal(self::FORM_MODAL);
        $this->toast((string) __($shift === null ? 'admin_oncall.toasts.created' : 'admin_oncall.toasts.updated'));
    }

    public function confirmDelete(int $id): void
    {
        $shift = OnCallShift::query()->find($id);

        if ($shift === null) {
            $this->toast((string) __('admin_oncall.errors.not_found'), 'error');

            return;
        }

        $this->authorize('delete', $shift);
        $this->deleteId = (int) $shift->getKey();
        $this->openModal(self::DELETE_MODAL);
    }

    public function delete(DeleteOnCallShift $delete): void
    {
        $shift = OnCallShift::query()->find($this->deleteId);
        $this->deleteId = 0;
        $this->closeModal(self::DELETE_MODAL);

        if ($shift === null) {
            $this->toast((string) __('admin_oncall.errors.not_found'), 'error');

            return;
        }

        $wasActive = $delete($this->user(), $shift);
        $this->toast((string) __($wasActive ? 'admin_oncall.toasts.deleted_active' : 'admin_oncall.toasts.deleted'), $wasActive ? 'warning' : 'success');
    }

    // --- Rendu --------------------------------------------------------------------------------------------------------

    public function render(OnCallShiftRules $rules): View
    {
        $tz = $this->displayTimezone();
        [$from, $to] = $this->window();
        $now = now();
        $kind = $this->form['kind'] ?? OnCallShift::KIND_ADMIN;

        /** @var Collection<int, OnCallShift> $shifts */
        $shifts = OnCallShift::query()
            ->with(['user:id,pseudo', 'zone:id,name'])
            ->where('starts_at', '<', $to->utc())
            ->where('ends_at', '>', $from->utc())
            ->chronological()
            ->get();

        /** @var Collection<int, OnCallShift> $current */
        $current = OnCallShift::query()->with(['user:id,pseudo', 'zone:id,name'])->active($now)->chronological()->get();
        $upcoming = OnCallShift::query()->with(['user:id,pseudo', 'zone:id,name'])->upcoming($now)->first();
        $deleting = $this->deleteId === 0 ? null : OnCallShift::query()->with(['user:id,pseudo', 'zone:id,name'])->find($this->deleteId);

        return view('livewire.admin.on-call.index', [
            'shifts' => $shifts,
            'current' => $current,
            'upcoming' => $upcoming,
            'from' => $from,
            'to' => $to->subDay(),
            'rangeLabel' => $this->rangeLabel($from, $to->subDay()),
            'isToday' => $this->day === '',
            'now' => $now,
            'format' => LocaleFormatter::make(null, $tz),
            'zones' => app(Referentials::class)->zones(),
            'users' => $rules->eligibleUsers(in_array($kind, OnCallShift::KINDS, true) ? $kind : OnCallShift::KIND_ADMIN),
            'kinds' => OnCallShift::KINDS,
            'deleting' => $deleting,
            'deleteWarning' => $deleting === null ? null : $this->deleteWarning($deleting),
            'fallbackActive' => ! app(OnCallRoster::class)->hasAdminOnCall($now),
            'timezone' => $tz,
        ])->layout('components.layouts.admin', [
            'title' => __('admin_oncall.title'),
            'adminPage' => 'garde',
        ]);
    }

    // --- Interne ------------------------------------------------------------------------------------------------------

    /**
     * Avertissement affiché avant la suppression d'une plage en cours (null : plage passée ou à venir).
     */
    private function deleteWarning(OnCallShift $shift): ?string
    {
        if (! $shift->isActive()) {
            return null;
        }

        if ($shift->isFocal()) {
            return (string) __('admin_oncall.delete.warning_focal');
        }

        $others = OnCallShift::query()->active()->forKind(OnCallShift::KIND_ADMIN)->whereKeyNot($shift->getKey())
            ->whereHas('user', fn (Builder $user) => $user->active())->exists();

        return (string) __($others ? 'admin_oncall.delete.warning_admin_others' : 'admin_oncall.delete.warning_admin_alone', [
            'roles' => implode(' / ', OnCallRoster::FALLBACK_ROLES),
        ]);
    }

    /**
     * Début (inclus) et fin (exclue) de la période affichée, dans le fuseau d'affichage.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(): array
    {
        $anchor = $this->anchor();

        if ($this->view === 'mois') {
            $start = $anchor->startOfMonth();

            return [$start, $start->addMonth()];
        }

        $start = $anchor->startOfWeek(CarbonImmutable::MONDAY);

        return [$start, $start->addWeek()];
    }

    private function anchor(): CarbonImmutable
    {
        $tz = $this->displayTimezone();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $this->day, $tz);

            if ($parsed instanceof CarbonImmutable && $parsed->format('Y-m-d') === $this->day) {
                return $parsed;
            }
        }

        return CarbonImmutable::now($tz)->startOfDay();
    }

    private function shiftAnchor(int $direction): CarbonImmutable
    {
        $anchor = $this->anchor();

        return $this->view === 'mois' ? $anchor->startOfMonth()->addMonthsNoOverflow($direction) : $anchor->addWeeks($direction);
    }

    private function rangeLabel(CarbonImmutable $from, CarbonImmutable $lastDay): string
    {
        $format = LocaleFormatter::make(null, $this->displayTimezone());

        return $this->view === 'mois'
            ? $format->monthYear($from)
            : (string) __('admin_oncall.range_week', ['from' => $format->shortDate($from), 'to' => $format->shortDate($lastDay)]);
    }

    private function displayTimezone(): string
    {
        return (string) config('xomiton.display_timezone', 'UTC');
    }

    /** Heure UTC en valeur d'un champ `datetime-local` (fuseau d'affichage). */
    private function toInput(CarbonInterface $date): string
    {
        return $date->copy()->timezone($this->displayTimezone())->format('Y-m-d\TH:i');
    }

    /** Valeur d'un champ `datetime-local` (fuseau d'affichage) en heure UTC ; null si illisible. */
    private function fromInput(string $value): ?CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, $this->displayTimezone());

        return $parsed instanceof CarbonImmutable ? $parsed->utc() : null;
    }

    /**
     * @return array<string, string>
     */
    private function blankForm(): array
    {
        $start = CarbonImmutable::now($this->displayTimezone())->addHour()->startOfHour();

        return [
            'kind' => OnCallShift::KIND_ADMIN,
            'user_id' => '',
            'zone_id' => '',
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHours(8)->format('Y-m-d\TH:i'),
            'notes' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return (array) __('admin_oncall.fields');
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
