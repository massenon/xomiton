<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Moderation;

use App\Application\Moderation\DecideReport;
use App\Application\Moderation\ReadReportedMessage;
use App\Domain\Moderation\Exceptions\ModerationRefused;
use App\Enums\BadWordSeverity;
use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\BadWord;
use App\Models\ModerationReport;
use App\Models\User;
use App\Models\UserWarning;
use App\Support\Audit;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Modération `/admin/moderation` (P12-06, P12-09, FR-SOC-06, RG-16, Q74 ; prototype admin/moderation.php) : KPI, file des
 * contenus signalés (publications, commentaires, sujets et réponses de forum, messages privés, comptes) — **un contenu = une
 * ligne** dans « À traiter », quel que soit le nombre de signalements —, décisions ignorer · masquer · avertir · suspendre
 * dans une modale (motif obligatoire pour avertir et suspendre), rouvrir un signalement traité, rendre visible un contenu
 * masqué (RG-16 ou décision), lecture du seul message privé signalé (Q74, journalisée), mots interdits (bloquer / signaler),
 * charte de bienveillance et suspensions récentes. Permission `moderation.manage` (admin, modérateur·rice, superadmin) ;
 * chaque décision passe par App\Application\Moderation\DecideReport (policy, journal, notification, jamais un compte
 * d'administration suspendu). La recherche ne porte que sur les colonnes en clair (extrait, motif, libellé du signalant·e).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    public const FILTERS = ['pending', 'handled', 'rejected', 'all'];

    #[SafeUrl(as: 'f', except: 'pending')]
    public string $filter = 'pending';

    #[SafeUrl(as: 'nat', except: '')]
    public string $kind = '';

    /** Signalement de la modale de décision / de lecture (jamais modifiable depuis le navigateur). */
    #[Locked]
    public ?int $reportId = null;

    #[Locked]
    public ?int $readingId = null;

    public string $decision = '';

    public string $reason = '';

    public string $wordText = '';

    public string $wordSeverity = 'signaler';

    public function mount(): void
    {
        $this->authorize('viewAny', ModerationReport::class);
    }

    // --- WithDataTable -----------------------------------------------------------------------------------------------

    protected function sortableColumns(): array
    {
        return ['date' => 'created_at', 'motif' => 'reason', 'nature' => 'kind'];
    }

    protected function searchableColumns(): array
    {
        return ['excerpt', 'reason', 'reporter_label'];
    }

    protected function defaultSort(): array
    {
        return ['date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['kind'];
    }

    // --- Données -----------------------------------------------------------------------------------------------------

    /**
     * @return array{pending: int, handled: int, suspensions: int, words: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'pending' => $this->pendingGroups()->toBase()->count(),
            'handled' => ModerationReport::query()->closed()->count(),
            'suspensions' => ModerationReport::query()->where('action', ModerationReport::ACTION_SUSPEND)->count(),
            'words' => BadWord::query()->active()->count(),
        ];
    }

    /** @return Collection<int, BadWord> */
    #[Computed]
    public function words(): Collection
    {
        return BadWord::query()->orderBy('word')->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function suspended(): Collection
    {
        return User::query()->suspended()->orderByDesc('suspended_at')->limit(5)->get(['id', 'pseudo', 'suspended_at', 'suspension_reason']);
    }

    #[Computed]
    public function report(): ?ModerationReport
    {
        return $this->reportId === null ? null : ModerationReport::query()->with(['reportedUser', 'subject'])->find($this->reportId);
    }

    /**
     * Message privé ouvert en lecture (Q74) : relu sans nouvelle ligne au journal (l'ouverture a été journalisée).
     *
     * @return array{author: ?string, sent_at: \Illuminate\Support\Carbon|null, body: string, has_media: bool}|null
     */
    #[Computed]
    public function message(): ?array
    {
        if ($this->readingId === null) {
            return null;
        }

        $report = ModerationReport::query()->find($this->readingId);

        try {
            return $report === null ? null : app(ReadReportedMessage::class)->peek($report, $this->actor());
        } catch (ModerationRefused) {
            return null;
        }
    }

    public function render(): View
    {
        $rows = $this->paginateQuery($this->filtered()->with(['reportedUser', 'reporter', 'handledBy', 'subject']));
        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.moderation.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => $this->formatter(),
            'siblings' => $this->siblings($rows->getCollection()),
            'warnings' => $this->warningCounts($rows->getCollection()),
            'charter' => (array) __('admin.moderation.charter.items'),
        ])->layout('components.layouts.admin', [
            'title' => __('admin.moderation.title'),
            'adminPage' => 'moderation',
        ]);
    }

    // --- Actions -----------------------------------------------------------------------------------------------------

    public function openDecision(int $id, string $decision): void
    {
        $report = ModerationReport::query()->findOrFail($id);
        $this->authorize('handle', $report);

        if (! isset(DecideReport::ACTIONS[$decision])) {
            return;
        }

        $this->reportId = (int) $report->getKey();
        $this->decision = $decision;
        $this->reason = '';
        $this->resetErrorBag();
        unset($this->report);
        $this->openModal('xmModerationDecide');
    }

    public function closeDecision(): void
    {
        $this->reset('reportId', 'decision', 'reason');
        $this->resetErrorBag();
        unset($this->report);
    }

    public function decide(DecideReport $decide): void
    {
        $report = $this->currentReport();

        if (in_array($this->decision, [DecideReport::WARN, DecideReport::SUSPEND], true)) {
            $this->validate(['reason' => ['required', 'string', 'min:3', 'max:'.DecideReport::REASON_MAX]], [], ['reason' => (string) __('admin.moderation.reason_label.'.$this->decision)]);
        }

        $closed = 0;

        if (! $this->attempt(function () use ($decide, $report, &$closed): void {
            $closed = $decide($report, $this->decision, $this->actor(), $this->reason);
        })) {
            return;
        }

        $decision = $this->decision;
        $this->closeModal('xmModerationDecide');
        $this->closeDecision();
        $this->afterChange();
        $this->toast(trans_choice('admin.moderation.toasts.'.$decision, $closed, ['count' => $closed]), $decision === DecideReport::DISMISS ? 'info' : 'success');
    }

    public function reopen(int $id, DecideReport $decide): void
    {
        $report = ModerationReport::query()->findOrFail($id);

        if ($this->attempt(fn () => $decide->reopen($report, $this->actor()))) {
            $this->afterChange();
            $this->toast((string) __('admin.moderation.toasts.reopened'), 'info');
        }
    }

    public function unhide(int $id, DecideReport $decide): void
    {
        $report = ModerationReport::query()->findOrFail($id);

        if ($this->attempt(fn () => $decide->unhide($report, $this->actor()))) {
            $this->afterChange();
            $this->toast((string) __('admin.moderation.toasts.unhidden'));
        }
    }

    /** Lecture du seul message signalé (Q74) : l'ouverture est journalisée, le texte n'est jamais conservé dans le composant. */
    public function openRead(int $id, ReadReportedMessage $read): void
    {
        $report = ModerationReport::query()->findOrFail($id);

        if (! $this->attempt(fn () => $read($report, $this->actor()))) {
            return;
        }

        $this->readingId = (int) $report->getKey();
        unset($this->message);
        $this->openModal('xmModerationRead');
    }

    public function closeRead(): void
    {
        $this->reset('readingId');
        unset($this->message);
    }

    public function addWord(): void
    {
        Gate::authorize('moderation.manage');

        $this->wordText = (string) preg_replace('/\s+/u', ' ', trim($this->wordText));
        $this->validate([
            'wordText' => ['required', 'string', 'min:3', 'max:40'],
            'wordSeverity' => ['required', Rule::in(array_map(static fn (BadWordSeverity $s): string => $s->value, BadWordSeverity::cases()))],
        ], [], [
            'wordText' => (string) __('admin.moderation.words.word'),
            'wordSeverity' => (string) __('admin.moderation.words.severity'),
        ]);

        $word = mb_strtolower($this->wordText);

        if (BadWord::query()->where('word', $word)->exists()) {
            $this->toast((string) __('admin.moderation.toasts.word_exists'), 'warning');

            return;
        }

        BadWord::query()->create(['word' => $word, 'severity' => $this->wordSeverity, 'is_active' => true]);
        Audit::log('moderation.word_added', null, 'severite='.$this->wordSeverity, $this->actor());

        $this->reset('wordText');
        unset($this->words, $this->stats);
        $this->toast((string) __('admin.moderation.toasts.word_added'));
    }

    public function removeWord(int $id): void
    {
        Gate::authorize('moderation.manage');

        $word = BadWord::query()->findOrFail($id);
        $word->delete();
        Audit::log('moderation.word_removed', null, 'severite='.$word->severity->value, $this->actor());

        unset($this->words, $this->stats);
        $this->toast((string) __('admin.moderation.toasts.word_removed'), 'info');
    }

    // --- Intérieur ---------------------------------------------------------------------------------------------------

    private function currentReport(): ModerationReport
    {
        abort_if($this->reportId === null, 404);

        return ModerationReport::query()->findOrFail($this->reportId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function attempt(callable $action): bool
    {
        try {
            $action();
        } catch (ModerationRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return false;
        }

        return true;
    }

    private function afterChange(): void
    {
        unset($this->stats, $this->suspended, $this->report, $this->message);
        $this->resetPage();
    }

    /**
     * Une ligne par contenu signalé en attente : le plus ancien signalement de chaque cible.
     *
     * @return Builder<ModerationReport>
     */
    private function pendingGroups(): Builder
    {
        $oldest = ModerationReport::query()->toBase()
            ->where('status', ModerationStatus::Pending->value)
            ->groupBy('subject_type', 'subject_id')
            ->selectRaw('min(id)');

        return ModerationReport::query()->whereIn('id', $oldest);
    }

    /**
     * @return Builder<ModerationReport>
     */
    private function filtered(): Builder
    {
        $query = match ($this->filter) {
            'handled' => ModerationReport::query()->withStatus(ModerationStatus::Handled),
            'rejected' => ModerationReport::query()->withStatus(ModerationStatus::Rejected),
            'all' => ModerationReport::query(),
            default => $this->pendingGroups(),
        };

        if (ModerationKind::tryFrom($this->kind) !== null) {
            $query->where('kind', $this->kind);
        }

        return $query;
    }

    /**
     * Signalements en attente par contenu de la page : nombre de signalements et de personnes distinctes.
     *
     * @param  Collection<int, ModerationReport>  $rows
     * @return array<string, array{reports: int, people: int}>
     */
    private function siblings(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $found = ModerationReport::query()->pending()->where(function (Builder $where) use ($rows): void {
            foreach ($rows as $row) {
                $where->orWhere(fn (Builder $one) => $one->where('subject_type', $row->subject_type)->where('subject_id', $row->subject_id));
            }
        })->toBase()->groupBy('subject_type', 'subject_id')
            ->selectRaw('subject_type, subject_id, count(*) as reports, count(distinct reporter_user_id) as people')->get();

        $map = [];

        foreach ($found as $line) {
            $map[$line->subject_type.':'.$line->subject_id] = ['reports' => (int) $line->reports, 'people' => (int) $line->people];
        }

        return $map;
    }

    /**
     * Avertissements reçus par les auteur·rices de la page.
     *
     * @param  Collection<int, ModerationReport>  $rows
     * @return array<int, int>
     */
    private function warningCounts(Collection $rows): array
    {
        $ids = $rows->pluck('reported_user_id')->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        return UserWarning::query()->whereIn('user_id', $ids)->toBase()->groupBy('user_id')->selectRaw('user_id, count(*) as total')
            ->pluck('total', 'user_id')->map(static fn (mixed $total): int => (int) $total)->all();
    }

    private function formatter(): LocaleFormatter
    {
        return LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
    }
}
