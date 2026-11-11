<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Application\Admin\Roles\ManagePeerRole;
use App\Application\Admin\Users\AccountSuspension;
use App\Application\Admin\Users\AdminRoles;
use App\Application\Admin\Users\ChangeUserRole;
use App\Application\Admin\Users\CreateUserAccount;
use App\Application\Admin\Users\DeleteUserAccount;
use App\Application\Admin\Users\NewAccountData;
use App\Application\Admin\Users\PermissionMatrix;
use App\Application\Admin\Users\ReviewRegistration;
use App\Application\Admin\Users\SendPasswordReset;
use App\Application\Shared\Referentials;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\UserStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\ModerationReport;
use App\Models\User;
use App\Models\UserWarning;
use App\Rules\PhoneBj;
use App\Rules\Pseudo;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Utilisateurs & rôles `/admin/utilisateurs` (P12-01, P12-09 ; prototype admin/utilisateurs.php) : KPI, comptes à valider
 * (validation des pair·es P2-10 réutilisée), liste filtrable (recherche sur les colonnes en clair, rôle, statut, département),
 * modale du compte (rôle, suspension, réactivation, réinitialisation du mot de passe par e-mail, suppression), création d'un
 * compte, export CSV, matrice des permissions en lecture. Lecture `users.view` (la vue du modérateur : liste sans e-mail
 * ni téléphone, seule action « Suspendre »). Chaque action rejoue sa policy (UserPolicy, RG-15) et son service
 * (App\Application\Admin\Users\*) ; un refus métier devient un toast d'erreur, un refus de droit un 403.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    #[SafeUrl(as: 'role', except: '')]
    public string $role = '';

    #[SafeUrl(as: 'st', except: '')]
    public string $status = '';

    #[SafeUrl(as: 'zone', except: '')]
    public string $zone = '';

    /** Compte ouvert dans la modale (jamais modifiable depuis le navigateur). */
    #[Locked]
    public ?int $accountId = null;

    public string $roleChoice = 'membre';

    public string $suspendReason = '';

    // --- Création d'un compte (modale) ---
    public string $newName = '';

    public string $newPseudo = '';

    public string $newEmail = '';

    public string $newPhone = '';

    public string $newZone = '';

    public string $newRole = 'membre';

    public bool $newInvite = true;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    // --- WithDataTable -----------------------------------------------------------------------------------------------

    protected function sortableColumns(): array
    {
        return ['compte' => 'pseudo', 'statut' => 'status', 'inscrit' => 'created_at', 'activite' => 'last_seen_at'];
    }

    /** Pseudo et nom pour tout le monde ; l'e-mail seulement pour qui le voit (`users.manage`). */
    protected function searchableColumns(): array
    {
        return $this->seesContacts() ? ['pseudo', 'name', 'email'] : ['pseudo', 'name'];
    }

    protected function defaultSort(): array
    {
        return ['inscrit', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['role', 'status', 'zone'];
    }

    public function export(): void
    {
        $this->requestCsvExport();
    }

    protected function csvScreen(): string
    {
        return 'utilisateurs';
    }

    protected function authorizeCsvExport(bool $full): void
    {
        $this->authorize('export', User::class);
    }

    protected function csvQuery(): Builder
    {
        return $this->filtered()->with(['roles', 'zone', 'profile']);
    }

    protected function csvColumns(bool $full): array
    {
        return [
            'id' => (string) __('admin.users.csv.id'),
            'name' => (string) __('admin.users.fields.name'),
            'pseudo' => (string) __('admin.users.fields.pseudo'),
            'email' => (string) __('admin.users.fields.email'),
            'role' => (string) __('admin.users.fields.role'),
            'status' => (string) __('admin.users.columns.status'),
            'zone' => (string) __('admin.users.fields.zone'),
            'joined' => (string) __('admin.users.columns.joined'),
        ];
    }

    protected function csvRow(Model $model, bool $full): array
    {
        /** @var User $model */
        return [
            'id' => (int) $model->getKey(),
            'name' => (string) $model->name,
            'pseudo' => (string) $model->pseudo,
            'email' => (string) $model->email,
            'role' => AdminRoles::label(AdminRoles::of($model)),
            'status' => $model->status->label(),
            'zone' => (string) $model->zone?->name,
            'joined' => $this->formatter()->isoDate($model->created_at),
        ];
    }

    // --- Données de la vue -------------------------------------------------------------------------------------------

    /**
     * @return array{total: int, active: int, pending: int, suspended: int}
     */
    #[Computed]
    public function stats(): array
    {
        $rows = User::query()->notAnonymized()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'total' => (int) $rows->sum(),
            'active' => (int) ($rows[UserStatus::Active->value] ?? 0),
            'pending' => (int) ($rows[UserStatus::Pending->value] ?? 0),
            'suspended' => (int) ($rows[UserStatus::Suspended->value] ?? 0),
        ];
    }

    /**
     * Demandes de compte en attente (10 plus anciennes) : validation des pair·es, P2-10.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    #[Computed]
    public function pending(): \Illuminate\Support\Collection
    {
        if (! auth()->user()?->can('users.validate_peers')) {
            return collect();
        }

        return User::query()->pending()->with(['profile', 'zone'])->orderBy('created_at')->limit(10)->get();
    }

    #[Computed]
    public function account(): ?User
    {
        return $this->accountId === null ? null : User::query()->with(['roles', 'profile', 'zone'])->find($this->accountId);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function accountFacts(): ?array
    {
        $account = $this->account;

        if ($account === null) {
            return null;
        }

        return [
            'role' => AdminRoles::of($account),
            'warnings' => UserWarning::query()->forUser($account)->count(),
            'reports' => ModerationReport::query()->againstUser($account)->count(),
        ];
    }

    /** @return array{roles: list<array{key: string, label: string, accounts: int}>, rows: list<array{key: string, levels: array<string, string>}>} */
    #[Computed]
    public function matrix(): array
    {
        return PermissionMatrix::build();
    }

    /** @return list<array{id: int, name: string}> */
    #[Computed]
    public function zones(): array
    {
        return array_map(static fn (array $zone): array => ['id' => $zone['id'], 'name' => $zone['name']], app(Referentials::class)->zones());
    }

    public function render(): View
    {
        $rows = $this->paginateQuery($this->filtered()->with(['roles', 'zone', 'profile']));
        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.users.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => $this->formatter(),
            'me' => auth()->user(),
            'seesContacts' => $this->seesContacts(),
            'assignable' => auth()->user() instanceof User ? AdminRoles::assignable(auth()->user()) : [],
        ])->layout('components.layouts.admin', [
            'title' => __('admin.users.title'),
            'adminPage' => 'utilisateurs',
        ]);
    }

    // --- Actions ------------------------------------------------------------------------------------------------------

    public function open(int $id): void
    {
        $account = User::query()->with('roles')->findOrFail($id);
        $this->authorize('view', $account);

        $this->accountId = (int) $account->getKey();
        $this->roleChoice = AdminRoles::of($account);
        $this->suspendReason = '';
        $this->resetErrorBag();
        unset($this->account, $this->accountFacts);
        $this->openModal('xmUserDetail');
    }

    public function closeDetail(): void
    {
        $this->reset('accountId', 'suspendReason');
        $this->resetErrorBag();
        unset($this->account, $this->accountFacts);
    }

    public function changeRole(ChangeUserRole $change): void
    {
        $account = $this->target();

        if ($this->attempt(fn () => $change($account, $this->roleChoice, $this->actor()))) {
            $this->refreshAfter();
            $this->toast((string) __('admin.users.toasts.role_changed', ['name' => $account->pseudo, 'role' => AdminRoles::label($this->roleChoice)]));
        }
    }

    /** Rôle pair en plus du rôle membre (un seul compte, rôles cumulables) : attribué ou retiré sans toucher aux autres rôles. */
    public function grantPeer(ManagePeerRole $peers): void
    {
        $account = $this->target();

        if ($this->attempt(fn () => $peers->grant($account, $this->actor()))) {
            $this->refreshAfter();
            $this->toast((string) __('admin.users.toasts.peer_granted', ['name' => $account->pseudo]));
        }
    }

    public function revokePeer(ManagePeerRole $peers): void
    {
        $account = $this->target();

        if ($this->attempt(fn () => $peers->revoke($account, $this->actor()))) {
            $this->refreshAfter();
            $this->toast((string) __('admin.users.toasts.peer_revoked', ['name' => $account->pseudo]), 'warning');
        }
    }

    public function suspend(AccountSuspension $suspension): void
    {
        $account = $this->target();
        $this->authorize('suspend', $account);
        $this->validate(['suspendReason' => ['required', 'string', 'min:3', 'max:'.AccountSuspension::REASON_MAX]], [], ['suspendReason' => (string) __('admin.users.suspend_reason')]);

        if ($this->attempt(fn () => $suspension->suspend($account, $this->actor(), $this->suspendReason))) {
            $this->suspendReason = '';
            $this->refreshAfter();
            $this->toast((string) __('admin.users.toasts.suspended', ['name' => $account->pseudo]), 'warning');
        }
    }

    public function reactivate(AccountSuspension $suspension): void
    {
        $account = $this->target();

        if ($this->attempt(fn () => $suspension->reactivate($account, $this->actor()))) {
            $this->refreshAfter();
            $this->toast((string) __('admin.users.toasts.reactivated', ['name' => $account->pseudo]));
        }
    }

    public function resetPassword(SendPasswordReset $reset): void
    {
        $account = $this->target();

        if ($this->attempt(fn () => $reset($account, $this->actor()))) {
            $this->toast((string) __('admin.users.toasts.password_reset', ['name' => $account->pseudo]), 'info');
        }
    }

    public function delete(DeleteUserAccount $delete): void
    {
        $account = $this->target();

        if ($this->attempt(fn () => $delete($account, $this->actor()))) {
            $this->closeModal('xmUserDetail');
            $this->closeDetail();
            $this->refreshAfter();
            $this->toast((string) __('admin.users.toasts.deleted', ['name' => $account->pseudo]), 'warning');
        }
    }

    /** Valider une demande de compte (P2-10) : depuis le panneau « Comptes à valider » ou la modale. */
    public function approve(int $id, ReviewRegistration $review): void
    {
        $account = User::query()->findOrFail($id);

        if ($this->attempt(fn () => $review->approve($account, $this->actor()))) {
            $this->refreshAfter();
            $this->toast((string) __('admin.users.toasts.approved', ['name' => $account->pseudo]));
        }
    }

    public function reject(int $id, ReviewRegistration $review): void
    {
        $account = User::query()->findOrFail($id);

        if ($this->attempt(fn () => $review->reject($account, $this->actor()))) {
            $this->closeModal('xmUserDetail');
            $this->closeDetail();
            $this->refreshAfter();
            $this->toast((string) __('admin.users.toasts.rejected', ['name' => $account->pseudo]), 'warning');
        }
    }

    public function create(CreateUserAccount $create): void
    {
        $this->authorize('create', User::class);
        $assignable = AdminRoles::assignable($this->actor());

        $validated = $this->validate([
            'newName' => ['required', 'string', 'min:3', 'max:120'],
            'newPseudo' => ['required', 'string', new Pseudo],
            'newEmail' => ['required', 'string', 'email:rfc', 'max:190', Rule::unique(User::class, 'email')],
            'newPhone' => ['nullable', 'string', 'max:30', new PhoneBj],
            'newZone' => ['nullable', 'integer', Rule::in(array_column($this->zones, 'id'))],
            'newRole' => ['required', Rule::in($assignable)],
        ], [
            'newEmail.unique' => (string) __('admin.users.errors.taken'),
            'newRole.in' => (string) __('admin.users.errors.superadmin_only_role'),
        ], $this->createAttributes());

        $created = null;
        $done = $this->attempt(function () use ($create, $validated, &$created): void {
            $created = $create(new NewAccountData(
                name: (string) $validated['newName'],
                pseudo: (string) $validated['newPseudo'],
                email: (string) $validated['newEmail'],
                role: (string) $validated['newRole'],
                phone: $validated['newPhone'] === '' ? null : $validated['newPhone'],
                zoneId: $validated['newZone'] === null || $validated['newZone'] === '' ? null : (int) $validated['newZone'],
                sendInvitation: $this->newInvite,
            ), $this->actor());
        });

        if ($done && $created instanceof User) {
            $this->reset('newName', 'newPseudo', 'newEmail', 'newPhone', 'newZone', 'newRole');
            $invited = $this->newInvite;
            $this->newInvite = true;
            $this->closeModal('xmUserCreate');
            $this->refreshAfter();
            $this->toast((string) __($invited ? 'admin.users.toasts.created_invited' : 'admin.users.toasts.created', ['name' => $created->pseudo]));
        }
    }

    // --- Intérieur -----------------------------------------------------------------------------------------------------

    /** Compte de la modale, sinon 404 (jamais un identifiant venu du navigateur : `accountId` est verrouillé). */
    private function target(): User
    {
        abort_if($this->accountId === null, 404);

        return User::query()->with(['roles', 'profile'])->findOrFail($this->accountId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Exécute une action métier : un refus (soi-même, dernier super-admin, compte pas en attente…) devient un toast d'erreur.
     */
    private function attempt(callable $action): bool
    {
        try {
            $action();
        } catch (AccountActionRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return false;
        }

        return true;
    }

    private function refreshAfter(): void
    {
        $this->roleChoice = $this->account instanceof User ? AdminRoles::of($this->account->refresh()->load('roles')) : $this->roleChoice;
        unset($this->stats, $this->pending, $this->account, $this->accountFacts, $this->matrix);
    }

    /**
     * @return Builder<User>
     */
    private function filtered(): Builder
    {
        $query = User::query()->notAnonymized();

        if (in_array($this->role, AdminRoles::ALL, true)) {
            $query->role($this->role);
        }

        if (UserStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        if (ctype_digit($this->zone) && $this->zone !== '') {
            $query->where('zone_id', (int) $this->zone);
        }

        return $query;
    }

    private function seesContacts(): bool
    {
        return (bool) auth()->user()?->can('users.manage');
    }

    private function formatter(): LocaleFormatter
    {
        return LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
    }

    /**
     * @return array<string, string>
     */
    private function createAttributes(): array
    {
        return [
            'newName' => (string) __('admin.users.fields.name'),
            'newPseudo' => (string) __('admin.users.fields.pseudo'),
            'newEmail' => (string) __('admin.users.fields.email'),
            'newPhone' => (string) __('admin.users.fields.phone'),
            'newZone' => (string) __('admin.users.fields.zone'),
            'newRole' => (string) __('admin.users.fields.role'),
        ];
    }
}
