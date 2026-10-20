<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Alerts;

use App\Application\Admin\Alerts\AlertDesk;
use App\Application\Alerts\AcknowledgeAlert;
use App\Application\Alerts\AddInternalNote;
use App\Application\Alerts\ChangeAlertStatus;
use App\Application\Alerts\ReassignAlert;
use App\Application\Alerts\RouteAlert;
use App\Application\Shared\Referentials;
use App\Domain\Alerts\Exceptions\AlertException;
use App\Domain\Alerts\TrackingCode;
use App\Domain\Alerts\ValueObjects\RoutingExplanation;
use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Alert;
use App\Models\AlertNote;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Policies\Scopes\AlertScope;
use App\Support\Audit;
use App\Support\EscalationPolicyFactory;
use App\Support\LikePattern;
use App\Support\LocaleFormatter;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Alertes & signalements `/admin/alertes` (P10-05, prototype admin/alertes.php ; WithDataTable, P10-02) : onglets
 * Toutes · SOS · Signalements · Besoins (Q29), filtres sur colonnes **en clair** (statut, priorité, département,
 * catégorie) et recherche `?q=` (n°, code de suivi, pseudo, catégorie, commune, département, personne routée : jamais
 * le récit chiffré, ADR-33), compteurs par onglet recalculés avec la liste, actualisation toutes les 10 s pour les
 * agent·es de garde et 30 s pour les autres (D7), export CSV des filtres actifs **sans récit, GPS, contact ni note**.
 *
 * Actions : prendre en charge (accusé de réception, AcknowledgeAlert), changer de statut (ChangeAlertStatus, historique
 * `alert_status_changes`), réattribuer (ReassignAlert, aperçu du routage RG-04 par RouteAlert::explain), note interne
 * chiffrée (AddInternalNote). Chaque cas d'usage autorise par la policy et écrit sa ligne d'audit. Le détail (récit,
 * contact, position, notes) n'est lu que dans la modale, après autorisation `view`, et l'ouverture est journalisée
 * (`alerts.viewed`) ; aucune donnée déchiffrée n'est une propriété publique (le snapshot Livewire n'en porte aucune).
 * Les notes internes n'apparaissent qu'à qui détient `alerts.manage` (policy `note`). Périmètre : AlertScope (un compte
 * `alerts.view_routed` ne voit que ses alertes routées).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    public const MODAL = 'xmAlertDetail';

    #[SafeUrl(as: 'onglet', except: 'all')]
    public string $tab = 'all';

    #[SafeUrl(as: 'st', except: '')]
    public string $status = '';

    #[SafeUrl(as: 'pr', except: '')]
    public string $priority = '';

    #[SafeUrl(as: 'zone', except: '')]
    public string $zone = '';

    #[SafeUrl(as: 'cat', except: '')]
    public string $category = '';

    /** Alerte ouverte dans la modale « Détail » (verrouillée : jamais modifiable depuis le navigateur). */
    #[Locked]
    public ?int $detailId = null;

    public string $newStatus = '';

    public string $reassignRole = '';

    public bool $reassignSameZone = true;

    public string $reassignTo = '';

    public string $noteBody = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Alert::class);

        if (! AlertDesk::isTab($this->tab)) {
            $this->tab = 'all';
        }
    }

    protected function sortableColumns(): array
    {
        return ['recue' => 'created_at', 'priorite' => 'priority', 'type' => 'type'];
    }

    /** Colonnes en clair de `alerts` (le récit, la position et le contact sont chiffrés : ADR-33) ; le reste est ajouté par applySearch(). */
    protected function searchableColumns(): array
    {
        return ['tracking_code', 'pseudo', 'category'];
    }

    protected function defaultSort(): array
    {
        return ['recue', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status', 'priority', 'zone', 'category'];
    }

    // --- Actions de la vue ---------------------------------------------------------------------------------------------

    public function setTab(string $tab): void
    {
        $this->tab = AlertDesk::isTab($tab) ? $tab : 'all';
        $this->clearSelection();
        $this->resetPage();
    }

    /** Prendre en charge depuis la liste : accusé de réception (arrête l'escalade RG-05), `nouveau → pris en charge`. */
    public function take(int $id): void
    {
        $alert = $this->findVisible($id);

        if ($alert === null) {
            return;
        }

        $this->acknowledge($alert);
    }

    public function openDetail(int $id): void
    {
        $alert = $this->findVisible($id);

        if ($alert === null) {
            return;
        }

        if (! $this->user()->can('view', $alert)) {
            $this->toast((string) __('admin.toasts.forbidden'), 'error');

            return;
        }

        $this->detailId = (int) $alert->getKey();
        $this->resetDetailForms();
        $this->resetErrorBag();
        unset($this->detail);
        Audit::log('alerts.viewed', $alert, null, $this->user());
        $this->openModal(self::MODAL);
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
        $this->resetDetailForms();
        $this->resetErrorBag();
        unset($this->detail);
    }

    public function acknowledgeDetail(): void
    {
        $alert = $this->detailAlert();

        if ($alert !== null) {
            $this->acknowledge($alert);
        }
    }

    public function changeStatus(): void
    {
        $alert = $this->detailAlert();

        if ($alert === null) {
            return;
        }

        $to = AlertStatus::tryFrom($this->newStatus);

        if ($to === null) {
            $this->addError('newStatus', (string) __('admin.alerts.errors.invalid_status'));

            return;
        }

        if ($this->guarded(fn () => app(ChangeAlertStatus::class)($alert, $to, $this->user()), 'newStatus') === null) {
            return;
        }

        $this->toast((string) __('admin.toasts.status_updated', ['status' => $to->label()]));
        $this->afterChange();
    }

    public function reassign(): void
    {
        $alert = $this->detailAlert();

        if ($alert === null) {
            return;
        }

        $contact = ctype_digit($this->reassignTo) ? DirectoryContact::query()->active()->find((int) $this->reassignTo) : null;

        if ($contact === null) {
            $this->addError('reassignTo', (string) __('admin.alerts.errors.contact_not_found'));

            return;
        }

        if ($this->guarded(fn () => app(ReassignAlert::class)($alert, $contact, $this->user()), 'reassignTo') === null) {
            return;
        }

        $this->toast((string) __('admin.toasts.reassigned', ['name' => $contact->name]));
        $this->afterChange();
    }

    /** Aperçu RG-04 : retient le contact que le routage automatique choisirait aujourd'hui. */
    public function useSuggested(int $contactId): void
    {
        if (DirectoryContact::query()->active()->whereKey($contactId)->exists()) {
            $this->reassignTo = (string) $contactId;
            $this->resetErrorBag('reassignTo');
        }
    }

    public function addNote(): void
    {
        $alert = $this->detailAlert();

        if ($alert === null) {
            return;
        }

        try {
            app(AddInternalNote::class)($alert, $this->user(), $this->noteBody);
        } catch (ValidationException $exception) {
            $this->addError('noteBody', (string) ($exception->errors()['body'][0] ?? __('admin.toasts.error_generic')));

            return;
        } catch (AuthorizationException) {
            $this->toast((string) __('admin.toasts.forbidden'), 'error');

            return;
        }

        $this->noteBody = '';
        $this->resetErrorBag('noteBody');
        $this->toast((string) __('admin.toasts.note_added'));
        unset($this->detail);
    }

    /** Export CSV des filtres actifs (WithDataTable : lien signé, diffusion par paquets, ligne d'audit). Jamais de récit ni de GPS. */
    public function export(): void
    {
        $this->requestCsvExport();
    }

    // --- Export CSV (ADR-33) -------------------------------------------------------------------------------------------

    protected function csvScreen(): string
    {
        return 'alertes';
    }

    protected function authorizeCsvExport(bool $full): void
    {
        abort_if($full, 403);
        $this->authorize('export', Alert::class);
    }

    protected function csvQuery(): Builder
    {
        return $this->filtered()->with(['zone:id,name', 'commune:id,name', 'routedContact:id,name']);
    }

    protected function csvColumns(bool $full): array
    {
        return [
            'id' => (string) __('admin.alerts.columns.code'),
            'tracking_code' => (string) __('admin.alerts.columns.tracking_code'),
            'received_at' => (string) __('admin.alerts.columns.received'),
            'type' => (string) __('admin.alerts.columns.type'),
            'priority' => (string) __('admin.alerts.columns.priority'),
            'status' => (string) __('admin.alerts.columns.status'),
            'zone' => (string) __('admin.alerts.columns.zone'),
            'commune' => (string) __('admin.alerts.columns.commune'),
            'category' => (string) __('admin.alerts.columns.category'),
            'source' => (string) __('admin.alerts.columns.source'),
            'routed_to' => (string) __('admin.alerts.columns.routed_to'),
            'acknowledged_at' => (string) __('admin.alerts.columns.acknowledged'),
        ];
    }

    protected function csvRow(Model $model, bool $full): array
    {
        /** @var Alert $model */
        $format = $this->formatter();

        return [
            'id' => (string) $model->getKey(),
            'tracking_code' => $model->displayCode(),
            'received_at' => $format->pattern('yyyy-MM-dd HH:mm:ss', $model->created_at),
            'type' => $model->type->label(),
            'priority' => $model->priority->label(),
            'status' => $model->status->label(),
            'zone' => $model->zone?->name,
            'commune' => $model->commune?->name,
            'category' => $this->categoryLabel($model->category),
            'source' => $model->source->label(),
            'routed_to' => $model->routedContact?->name,
            'acknowledged_at' => $model->acknowledged_at === null ? null : $format->pattern('yyyy-MM-dd HH:mm:ss', $model->acknowledged_at),
        ];
    }

    // --- Requêtes ------------------------------------------------------------------------------------------------------

    /**
     * Recherche `?q=` : colonnes en clair de `alerts`, n° (`#12`), code de suivi, et noms de commune, de département et
     * de personne routée. Jamais le récit, la position ni le contact (chiffrés, ADR-33).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySearch(Builder $query): Builder
    {
        self::assertClearColumns($query->getModel(), $this->searchableColumns(), 'recherche');
        $term = $this->searchTerm();

        if ($term === '') {
            return $query;
        }

        $pattern = LikePattern::contains($term);
        $number = preg_match('/^#?(\d{1,10})$/', $term, $matches) === 1 ? (int) $matches[1] : null;
        $code = TrackingCode::normalize($term);

        return $query->where(function (Builder $where) use ($pattern, $number, $code): void {
            foreach (['pseudo', 'category'] as $column) {
                $where->orWhereRaw(LikePattern::clause($where->qualifyColumn($column)), [$pattern]);
            }

            if ($number !== null) {
                $where->orWhere($where->qualifyColumn('id'), $number);
            }

            if ($code !== null) {
                $where->orWhere($where->qualifyColumn('tracking_code'), $code);
            }

            $where->orWhereHas('commune', fn (Builder $q) => $q->whereRaw(LikePattern::clause('communes.name'), [$pattern]));
            $where->orWhereHas('zone', fn (Builder $q) => $q->whereRaw(LikePattern::clause('zones.name'), [$pattern]));
            $where->orWhereHas('routedContact', fn (Builder $q) => $q->whereRaw(LikePattern::clause('directory_contacts.name'), [$pattern]));
        });
    }

    /**
     * Alertes visibles (AlertScope) sous les filtres, onglet compris ; `$withType = false` pour les compteurs d'onglets.
     *
     * @return Builder<Alert>
     */
    private function filtered(bool $withType = true): Builder
    {
        $query = AlertScope::routedTo($this->user());

        if (($status = AlertStatus::tryFrom($this->status)) !== null) {
            $query->where('status', $status);
        }

        if (($priority = AlertPriority::tryFrom($this->priority)) !== null) {
            $query->where('priority', $priority);
        }

        if ($this->zone !== '' && ctype_digit($this->zone)) {
            $query->where('zone_id', (int) $this->zone);
        }

        if ($this->category !== '' && preg_match('/^[a-z0-9_-]{1,20}$/', $this->category) === 1) {
            $query->where('category', $this->category);
        }

        if ($withType && ($type = AlertDesk::tabType($this->tab)) !== null) {
            $query->where('type', $type);
        }

        return $query;
    }

    /** Alerte du périmètre de la personne, ou null (toast « introuvable » : jamais une 404 dans une action Livewire). */
    private function findVisible(int $id): ?Alert
    {
        $alert = AlertScope::routedTo($this->user())->whereKey($id)->first();

        if ($alert === null) {
            $this->toast((string) __('admin.alerts.errors.not_found'), 'error');
            $this->closeDetail();
        }

        return $alert;
    }

    /**
     * Identifiants des alertes « nouveau » de la page que la personne peut prendre en charge (policy `take`), calculés
     * en une fois : un `@can` par ligne ferait une requête `isRoutedTo` par alerte pour un compte `alerts.view_routed`.
     *
     * @param  SupportCollection<int, Alert>  $page
     * @return list<int>
     */
    private function takeableIds(SupportCollection $page): array
    {
        $user = $this->user();
        $ids = $page->filter(static fn (Alert $alert): bool => $alert->status === AlertStatus::New)
            ->map(static fn (Alert $alert): int => (int) $alert->getKey())->values()->all();

        if ($ids === [] || ! $user->can('alerts.take')) {
            return [];
        }

        if ($user->can('alerts.view')) {
            return $ids;
        }

        return AlertScope::routedTo($user)->whereIn('id', $ids)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    private function detailAlert(): ?Alert
    {
        return $this->detailId === null ? null : $this->findVisible($this->detailId);
    }

    private function acknowledge(Alert $alert): void
    {
        try {
            $result = app(AcknowledgeAlert::class)->byUser($alert, $this->user());
        } catch (AuthorizationException) {
            $this->toast((string) __('admin.toasts.forbidden'), 'error');

            return;
        }

        if ($result->closed) {
            $this->toast((string) __('admin.alerts.errors.invalid_status'), 'error');
        } elseif ($result->alreadyAcknowledged) {
            $name = $result->alert->acknowledgedBy?->pseudo ?? (string) __('admin.alerts.detail.someone');
            $this->toast((string) __('admin.alerts.errors.already_taken', ['name' => $name]), 'info');
        } else {
            $this->toast((string) __('admin.toasts.taken'));
        }

        $this->afterChange();
    }

    /**
     * Exécute un cas d'usage en traduisant refus et erreurs métier en message du champ ou en toast.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $action
     * @return TResult|null
     */
    private function guarded(callable $action, string $field): mixed
    {
        try {
            return $action();
        } catch (AuthorizationException) {
            $this->toast((string) __('admin.toasts.forbidden'), 'error');
        } catch (AlertException $exception) {
            $this->addError($field, $exception->userMessage());
        }

        return null;
    }

    private function afterChange(): void
    {
        $this->resetDetailForms();
        unset($this->detail);
    }

    private function resetDetailForms(): void
    {
        $this->reset(['newStatus', 'reassignTo', 'reassignRole', 'noteBody']);
        $this->reassignSameZone = true;
    }

    // --- Données de la vue ---------------------------------------------------------------------------------------------

    /**
     * Détail de l'alerte ouverte : récit, contact et position déchiffrés à la demande (après `view`), historique,
     * notes (seulement avec `note`), aperçu du routage. Recalculé à chaque requête, jamais sérialisé.
     *
     * @return array{
     *     alert: Alert,
     *     needs: list<string>,
     *     category: string|null,
     *     timeline: list<array{at: CarbonInterface, kind: string, text: string, by: string|null, internal: bool}>,
     *     notes_visible: bool,
     *     transitions: list<AlertStatus>,
     *     can_take: bool,
     *     can_note: bool,
     *     can_reassign: bool,
     *     contacts: Collection<int, DirectoryContact>,
     *     routing: RoutingExplanation|null,
     *     routing_contacts: array<int, string>,
     *     attachments: list<array{kind: string, size: int, url: string}>,
     *     escalation_at: CarbonInterface|null,
     * }|null
     */
    #[Computed]
    public function detail(): ?array
    {
        if ($this->detailId === null) {
            return null;
        }

        $user = $this->user();
        $alert = AlertScope::routedTo($user)->whereKey($this->detailId)->first();

        if ($alert === null || ! Gate::forUser($user)->allows('view', $alert)) {
            return null;
        }

        $alert->load(['zone', 'commune', 'routedContact', 'acknowledgedBy:id,pseudo', 'acknowledgedByContact:id,name', 'device:id,label,platform', 'attachments']);
        $canNote = Gate::forUser($user)->allows('note', $alert);
        $canReassign = Gate::forUser($user)->allows('reassign', $alert);
        $terminal = $alert->isCancelled() || $alert->status === AlertStatus::Closed;
        $needsMap = collect(app(Referentials::class)->needs())->pluck('label', 'slug')->all();
        $routing = null;
        $names = [];
        $contacts = new Collection;

        if ($canReassign && ! $terminal) {
            $routing = app(RouteAlert::class)->explain($alert->zone_id === null ? null : (int) $alert->zone_id, $alert->needSlugs());
            $contacts = app(AlertDesk::class)->contactsFor($alert, $this->reassignRole, $this->reassignSameZone);
            $names = $this->routingNames($routing);

            if (ctype_digit($this->reassignTo) && ! $contacts->contains('id', (int) $this->reassignTo)) {
                $selected = DirectoryContact::query()->active()->with('zone:id,name')->find((int) $this->reassignTo);
                $selected === null || $contacts->push($selected);
            }
        }

        return [
            'alert' => $alert,
            'needs' => array_map(static fn (string $slug): string => (string) ($needsMap[$slug] ?? $slug), $alert->needSlugs()),
            'category' => $this->categoryLabel($alert->category),
            'timeline' => $this->timeline($alert, $canNote),
            'notes_visible' => $canNote,
            'transitions' => array_values(array_filter(
                $alert->status->allowedTransitions(),
                static fn (AlertStatus $to): bool => Gate::forUser($user)->allows(ChangeAlertStatus::ability($to), $alert),
            )),
            'can_take' => ! $alert->isAcknowledged() && ! $terminal && Gate::forUser($user)->allows('take', $alert),
            'can_note' => $canNote,
            'can_reassign' => $canReassign && ! $terminal,
            'contacts' => $contacts,
            'routing' => $routing,
            'routing_contacts' => $names,
            'attachments' => $alert->attachments->map(static fn ($attachment): array => [
                'kind' => (string) $attachment->kind,
                'size' => (int) $attachment->size,
                'url' => $attachment->signedUrl(),
            ])->values()->all(),
            'escalation_at' => $this->escalationAt($alert),
        ];
    }

    /**
     * Historique unifié, du plus récent au plus ancien : statuts, escalades / réattributions et notes internes (si permis).
     *
     * @return list<array{at: CarbonInterface, kind: string, text: string, by: string|null, internal: bool}>
     */
    private function timeline(Alert $alert, bool $withNotes): array
    {
        $format = $this->formatter();
        $entries = [];

        foreach ($alert->statusChanges()->with('user:id,pseudo')->chronological()->get() as $change) {
            $entries[] = [
                'at' => $change->created_at,
                'kind' => 'status',
                'text' => $change->isInitial()
                    ? (string) __('admin.alerts.timeline.created', ['status' => $change->to_status->label(), 'user' => $change->user?->pseudo ?? (string) __('admin.alerts.timeline.system')])
                    : (string) __('admin.alerts.detail.status_change', [
                        'from' => $change->from_status->label(),
                        'to' => $change->to_status->label(),
                        'user' => $change->user?->pseudo ?? (string) __('admin.alerts.timeline.system'),
                        'date' => $format->tableDateTime($change->created_at),
                    ]),
                'by' => null,
                'internal' => false,
            ];
        }

        $contactNames = DirectoryContact::withTrashed()
            ->whereIn('id', $alert->escalations()->pluck('to_contact_id')->filter()->all())
            ->pluck('name', 'id');

        foreach ($alert->escalations()->orderBy('id')->get() as $escalation) {
            $entries[] = [
                'at' => $escalation->created_at,
                'kind' => 'routing',
                'text' => (string) __('admin.alerts.timeline.escalation.'.$escalation->reason, [
                    'name' => (string) ($contactNames[$escalation->to_contact_id] ?? __('admin.alerts.detail.someone')),
                ]),
                'by' => null,
                'internal' => false,
            ];
        }

        if ($withNotes) {
            foreach ($alert->notes()->with('author:id,pseudo')->latestFirst()->get() as $note) {
                /** @var AlertNote $note */
                $entries[] = [
                    'at' => $note->created_at,
                    'kind' => 'note',
                    'text' => (string) $note->body,
                    'by' => $note->author?->pseudo,
                    'internal' => true,
                ];
            }
        }

        usort($entries, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return $entries;
    }

    /** Noms des contacts examinés / retenus par l'aperçu du routage (une requête). */
    private function routingNames(RoutingExplanation $routing): array
    {
        $ids = [];

        foreach ($routing->levels as $level) {
            array_push($ids, ...$level['examined']);

            if ($level['matched'] !== null) {
                $ids[] = $level['matched'];
            }
        }

        return $ids === [] ? [] : DirectoryContact::query()->whereIn('id', array_unique($ids))->pluck('name', 'id')->map(static fn (mixed $name): string => (string) $name)->all();
    }

    private function escalationAt(Alert $alert): ?CarbonInterface
    {
        if ($alert->isAcknowledged() || $alert->isEscalated() || $alert->isCancelled() || $alert->isTerminal() || $alert->safe_at !== null) {
            return null;
        }

        $minutes = EscalationPolicyFactory::fromConfig()->delaysFor($alert->type)->escalateAfterMinutes;

        return $minutes === null ? null : $alert->created_at?->copy()->addMinutes($minutes);
    }

    private function categoryLabel(?string $slug): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        foreach (app(Referentials::class)->violenceTypes() as $type) {
            if ($type['slug'] === $slug) {
                return $type['label'];
            }
        }

        return $slug;
    }

    public function render(): View
    {
        $user = $this->user();
        $desk = app(AlertDesk::class);
        $rows = $this->paginateQuery($this->filtered()->with(['zone:id,name', 'commune:id,name', 'routedContact:id,name,role'])
            ->select('alerts.*')->selectRaw('(alerts.lat IS NOT NULL) as has_gps'));
        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.alerts.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => $this->formatter(),
            'kpis' => $desk->kpis(AlertScope::routedTo($user)),
            'tabCounts' => $desk->tabCounts($this->applySearch($this->filtered(false))),
            'pollSeconds' => $desk->pollSeconds($user),
            'takeable' => $this->takeableIds($rows->getCollection()),
            'zones' => app(Referentials::class)->zones(),
            'categories' => app(Referentials::class)->violenceTypes(),
            'types' => AlertType::cases(),
            'canExport' => $user->can('export', Alert::class),
        ])->layout('components.layouts.admin', [
            'title' => __('admin.alerts.title'),
            'adminPage' => 'alertes',
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function formatter(): LocaleFormatter
    {
        return LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
    }
}
