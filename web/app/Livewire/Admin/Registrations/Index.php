<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Registrations;

use App\Application\Account\RoleRequests\ReviewRoleRequest;
use App\Application\Admin\Registrations\RegistrationSettings;
use App\Application\Admin\Roles\TeamInvitations;
use App\Application\Admin\Users\ReviewRegistration;
use App\Application\Auth\Support\RegistrationGate;
use App\Application\Shared\Referentials;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Domain\Account\Exceptions\RoleRequestRefused;
use App\Enums\UserStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\RoleInvitation;
use App\Models\RoleRequest;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Inscriptions `/admin/inscriptions` (P12-02, FR-AUTH-06, Q26 ; prototype admin/inscriptions.php) : état ouvert / fermé et
 * interrupteur, options (validation manuelle « pair·es seulement » ou « tous », message de fermeture ≤ 300 caractères lu
 * par la page des inscriptions fermées, date de réouverture, plage horaire quotidienne à l'heure du Bénin), inscriptions
 * par semaine (8 semaines, tableau lisible sous les barres), demandes en attente (approuver / rejeter, ReviewRegistration)
 * et dix dernières inscriptions. Permission `registrations.manage` (admin, superadmin) ; la validation d'un compte
 * demande en plus `users.validate_peers` (policy). Le prototype ne réglait qu'un interrupteur : le mode « tous » est ajouté
 * par Q26 ; la validation des pair·es reste toujours obligatoire (RG-03).
 */
final class Index extends Component
{
    use InteractsWithUi;

    public string $manual = RegistrationSettings::MANUAL_PEERS;

    public string $message = '';

    public string $reopenAt = '';

    public bool $windowOn = false;

    public string $windowStart = '08:00';

    public string $windowEnd = '20:00';

    /** Motif écrit pour la personne quand une demande est rejetée ou refusée (≤ 200 caractères). */
    public string $rejectReason = '';

    /** Motif écrit pour la personne quand une demande de rôle pair est refusée (obligatoire, 3 à 200 caractères). */
    public string $roleRejectReason = '';

    // --- Invitations à l'équipe (lien / code à usage unique) ---
    public string $inviteRole = 'pair';

    public string $inviteEmail = '';

    public string $inviteNote = '';

    /** Département de rattachement de la personne invitée (vide : aucun). */
    public string $inviteZone = '';

    /** Durée de validité du lien (jours). */
    public int $inviteDays = 7;

    /** Dernier lien créé : montré une fois (le code n'est jamais conservé en clair). @var array{code: string, link: string, role: string}|null */
    public ?array $createdInvite = null;

    public function mount(): void
    {
        $this->inviteDays = TeamInvitations::defaultDays();
        abort_unless(auth()->user()?->can('registrations.manage'), 403);

        $current = RegistrationSettings::current();
        $this->manual = $current['manual'];
        $this->message = $current['message'];
        $this->reopenAt = $current['reopen_at'];
        $this->windowOn = $current['window_on'];
        $this->windowStart = $current['window_start'];
        $this->windowEnd = $current['window_end'];
    }

    public function setOpen(bool $open, RegistrationSettings $settings): void
    {
        $settings->open($open, $this->actor());
        unset($this->state);
        $this->toast((string) __($open ? 'admin.registrations.toasts.opened' : 'admin.registrations.toasts.closed'), $open ? 'success' : 'warning');
    }

    public function save(RegistrationSettings $settings): void
    {
        abort_unless($this->actor()->can('registrations.manage'), 403);

        $this->validate([
            'manual' => ['required', Rule::in([RegistrationSettings::MANUAL_PEERS, RegistrationSettings::MANUAL_ALL])],
            'message' => ['nullable', 'string', 'max:'.RegistrationSettings::MESSAGE_MAX],
            'reopenAt' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'windowOn' => ['boolean'],
            'windowStart' => [Rule::requiredIf($this->windowOn), 'nullable', 'date_format:H:i'],
            'windowEnd' => [Rule::requiredIf($this->windowOn), 'nullable', 'date_format:H:i'],
        ], [
            'message.max' => (string) __('admin.registrations.errors.message_max', ['max' => RegistrationSettings::MESSAGE_MAX]),
            'reopenAt.date_format' => (string) __('admin.registrations.errors.reopen_invalid'),
        ], [
            'manual' => (string) __('admin.registrations.manual.label'),
            'message' => (string) __('admin.registrations.closed_message'),
            'reopenAt' => (string) __('admin.registrations.reopen_at'),
            'windowStart' => (string) __('admin.registrations.window_start'),
            'windowEnd' => (string) __('admin.registrations.window_end'),
        ]);

        if ($this->windowOn && $this->windowStart >= $this->windowEnd) {
            $this->addError('windowEnd', (string) __('admin.registrations.errors.window_order'));

            return;
        }

        $settings->saveOptions([
            'manual' => $this->manual,
            'message' => $this->message,
            'reopen_at' => $this->reopenAt,
            'window_on' => $this->windowOn,
            'window_start' => $this->windowStart,
            'window_end' => $this->windowEnd,
        ], $this->actor());

        unset($this->state);
        $this->toast((string) __('admin.registrations.toasts.saved'));
    }

    public function approve(int $id, ReviewRegistration $review): void
    {
        $account = User::query()->findOrFail($id);

        if ($this->attempt(fn () => $review->approve($account, $this->actor()))) {
            unset($this->pending, $this->kpis);
            $this->toast((string) __('admin.registrations.toasts.approved', ['name' => $account->pseudo]));
        }
    }

    public function reject(int $id, ReviewRegistration $review): void
    {
        $account = User::query()->findOrFail($id);

        if ($this->attempt(fn () => $review->reject($account, $this->actor(), $this->rejectReason))) {
            $this->rejectReason = '';
            unset($this->pending, $this->kpis);
            $this->toast((string) __('admin.registrations.toasts.rejected', ['name' => $account->pseudo]), 'warning');
        }
    }

    public function approveRoleRequest(int $id, ReviewRoleRequest $review): void
    {
        $request = RoleRequest::query()->with('user')->findOrFail($id);

        if ($this->attempt(fn () => $review->approve($request, $this->actor()))) {
            unset($this->roleRequests);
            $this->toast((string) __('admin.registrations.role_requests.toasts.approved', ['name' => $request->user->pseudo]));
        }
    }

    public function rejectRoleRequest(int $id, ReviewRoleRequest $review): void
    {
        $request = RoleRequest::query()->with('user')->findOrFail($id);

        if ($this->attempt(fn () => $review->reject($request, $this->actor(), $this->roleRejectReason))) {
            $this->roleRejectReason = '';
            unset($this->roleRequests);
            $this->toast((string) __('admin.registrations.role_requests.toasts.rejected', ['name' => $request->user->pseudo]), 'warning');
        }
    }

    /**
     * Demandes du rôle pair faites par des membres depuis leur profil (D-MOB-15), les plus anciennes d'abord.
     *
     * @return Collection<int, RoleRequest>
     */
    #[Computed]
    public function roleRequests(): Collection
    {
        return RoleRequest::query()->pending()->with(['user.zone', 'user.profile'])->orderBy('id')->limit(25)->get();
    }

    public function invite(TeamInvitations $invitations): void
    {
        $actor = $this->actor();
        abort_unless($actor->can('peers.invite') || $actor->can('roles.assign_admin'), 403);

        $this->validate([
            'inviteRole' => ['required', Rule::in(TeamInvitations::ROLES)],
            'inviteEmail' => ['nullable', 'email:rfc', 'max:190'],
            'inviteNote' => ['nullable', 'string', 'max:190'],
            'inviteZone' => ['nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
            'inviteDays' => ['required', 'integer', 'min:'.TeamInvitations::minDays(), 'max:'.TeamInvitations::maxDays()],
        ], [], [
            'inviteZone' => (string) __('admin.registrations.invitations.zone'),
            'inviteDays' => (string) __('admin.registrations.invitations.days'),
            'inviteEmail' => (string) __('admin.registrations.invitations.email'),
            'inviteNote' => (string) __('admin.registrations.invitations.note'),
        ]);

        $created = null;

        if ($this->attempt(function () use ($invitations, $actor, &$created): void {
            $created = $invitations->create($actor, $this->inviteRole, $this->inviteEmail, $this->inviteNote, $this->inviteZone === '' ? null : (int) $this->inviteZone, $this->inviteDays);
        }) && is_array($created)) {
            $this->createdInvite = ['code' => $created['code'], 'link' => $created['link'], 'role' => $this->inviteRole];
            $this->reset('inviteEmail', 'inviteNote', 'inviteZone');
            unset($this->invitationList);
            $this->toast((string) __('admin.registrations.invitations.created'));
        }
    }

    public function revokeInvitation(int $id, TeamInvitations $invitations): void
    {
        $invitation = RoleInvitation::query()->findOrFail($id);

        if ($this->attempt(fn () => $invitations->revoke($invitation, $this->actor()))) {
            unset($this->invitationList);
            $this->toast((string) __('admin.registrations.invitations.revoked'), 'warning');
        }
    }

    /**
     * Invitations récentes (20 plus récentes) ; les rôles de modération et d'administration ne se voient qu'avec
     * `roles.assign_admin`.
     *
     * @return Collection<int, RoleInvitation>
     */
    #[Computed]
    public function invitationList(): Collection
    {
        $actor = $this->actor();

        return RoleInvitation::query()
            ->when(! $actor->can('roles.assign_admin'), fn ($query) => $query->where('role', 'pair'))
            ->with(['inviter:id,pseudo', 'user:id,pseudo', 'zone:id,name'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * État effectif des inscriptions à cet instant (interrupteur et plage horaire) : ce que voit la page publique.
     *
     * @return array{open: bool, effective: bool, message: string}
     */
    #[Computed]
    public function state(): array
    {
        return [
            'open' => RegistrationSettings::current()['open'],
            'effective' => RegistrationGate::isOpen(),
            'message' => RegistrationGate::closedMessage(),
        ];
    }

    /**
     * @return array{week: int, pending: int, total: int}
     */
    #[Computed]
    public function kpis(): array
    {
        return [
            'week' => User::query()->notAnonymized()->where('created_at', '>=', now()->subDays(7))->count(),
            'pending' => User::query()->where('status', UserStatus::Pending)->count(),
            'total' => User::query()->notAnonymized()->count(),
        ];
    }

    /**
     * Huit dernières semaines (lundi → dimanche, heure du Bénin) : libellé du lundi et nombre d'inscriptions.
     *
     * @return list<array{label: string, count: int, share: int}>
     */
    #[Computed]
    public function weeks(): array
    {
        $timezone = (string) config('xomiton.display_timezone', 'UTC');
        $first = Carbon::now($timezone)->startOfWeek()->subWeeks(7);
        $format = $this->formatter();
        $counts = array_fill(0, 8, 0);

        User::query()->notAnonymized()->where('created_at', '>=', $first->copy()->utc())->toBase()->orderBy('id')
            ->pluck('created_at')
            ->each(function (mixed $createdAt) use ($first, $timezone, &$counts): void {
                $week = (int) floor($first->diffInDays(Carbon::parse($createdAt, 'UTC')->setTimezone($timezone)->startOfDay(), false) / 7);

                if (isset($counts[$week])) {
                    $counts[$week]++;
                }
            });

        $max = max(1, ...$counts);

        return array_map(static fn (int $count, int $index): array => [
            'label' => $format->dayMonth($first->copy()->addWeeks($index)),
            'count' => $count,
            'share' => (int) round($count / $max * 100),
        ], $counts, array_keys($counts));
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function pending(): Collection
    {
        return User::query()->pending()->with(['profile', 'zone'])->orderBy('created_at')->limit(25)->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function recent(): Collection
    {
        return User::query()->notAnonymized()->with('zone')->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get();
    }

    public function render(): View
    {
        return view('livewire.admin.registrations.index', [
            'format' => $this->formatter(),
            'canValidate' => (bool) auth()->user()?->can('users.validate_peers'),
            'canAssignPeer' => (bool) auth()->user()?->can('peers.assign'),
            'inviteZones' => array_map(static fn (array $zone): array => ['id' => $zone['id'], 'name' => $zone['name']], app(Referentials::class)->zones()),
            'inviteMinDays' => TeamInvitations::minDays(),
            'inviteMaxDays' => TeamInvitations::maxDays(),
            'canInvite' => (bool) (auth()->user()?->can('peers.invite') || auth()->user()?->can('roles.assign_admin')),
            'canInviteAdmin' => (bool) auth()->user()?->can('roles.assign_admin'),
            'messageMax' => RegistrationSettings::MESSAGE_MAX,
        ])->layout('components.layouts.admin', [
            'title' => __('admin.registrations.title'),
            'adminPage' => 'inscriptions',
        ]);
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
        } catch (AccountActionRefused|RoleRequestRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return false;
        }

        return true;
    }

    private function formatter(): LocaleFormatter
    {
        return LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
    }
}
