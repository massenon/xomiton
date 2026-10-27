{{--
    Utilisateurs & rôles (P12-01, P12-09) : composant App\Livewire\Admin\Users\Index, gabarit admin.
    Ordre UI_UX_DESIGN_SYSTEM.md §4 : introduction · KPI · comptes à valider (P2-10) · carte « liste » (en-tête + actions,
    filtres, tableau, pied) · matrice des permissions en lecture · modales (compte, création) toujours montées.
    La vue du modérateur (`users.view` seul) : liste sans e-mail ni téléphone, bouton « Suspendre » comme seule action.
--}}
<div class="xm-users">
    <p class="text-secondary mb-4 xm-prose">{{ $seesContacts ? __('admin.users.intro') : __('admin.users.intro_moderator') }}</p>

    <x-admin.kpi id="xmUsersKpis" :items="[
        ['label' => __('admin.users.kpi.total'), 'value' => $format->number($this->stats['total']), 'icon' => 'people'],
        ['label' => __('admin.users.kpi.active'), 'value' => $format->number($this->stats['active']), 'icon' => 'person-check', 'variant' => 'success'],
        ['label' => __('admin.users.kpi.pending'), 'value' => $format->number($this->stats['pending']), 'icon' => 'hourglass-split', 'variant' => 'warning'],
        ['label' => __('admin.users.kpi.suspended'), 'value' => $format->number($this->stats['suspended']), 'icon' => 'person-slash', 'variant' => 'danger'],
    ]" />

    @can('users.validate_peers')
        @if ($this->pending->isNotEmpty())
            <section class="card xm-admin-card border-warning mb-4" aria-labelledby="xmUsersPendingTitle">
                <div class="card-header bg-warning-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h2 class="h6 fw-bold mb-0" id="xmUsersPendingTitle"><i class="bi bi-hourglass-split me-2" aria-hidden="true"></i>{{ trans_choice('admin.users.pending_count', $this->stats['pending'], ['count' => $format->number($this->stats['pending'])]) }}</h2>
                    <a wire:navigate wire:navigate.hover class="btn btn-sm btn-light border" href="{{ route('admin.inscriptions') }}">{{ __('admin.users.pending_all') }}</a>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($this->pending as $request)
                        <li class="list-group-item d-flex flex-wrap align-items-center gap-3" wire:key="pending-{{ $request->id }}">
                            <x-avatar :name="$request->pseudo" :src="$request->avatar_url" size="sm" />
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-break">{{ $request->pseudo }}@if ($request->name) <span class="text-secondary fw-normal">· {{ $request->name }}</span>@endif</div>
                                <div class="small text-secondary">
                                    {{ $request->email }}@if ($request->zone) · {{ $request->zone->name }}@endif
                                    · {{ __('admin.users.columns.requested_role', ['role' => $request->profile?->member_type?->label() ?? __('admin.users.roles.membre')]) }}
                                    · {{ $format->ago($request->created_at) }}
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $request->id }})" wire:loading.attr="disabled" wire:target="approve({{ $request->id }})">
                                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin.users.validate') }}<span class="visually-hidden"> {{ $request->pseudo }}</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reject({{ $request->id }})" wire:confirm="{{ __('admin.users.confirm.reject', ['name' => $request->pseudo]) }}" wire:loading.attr="disabled" wire:target="reject({{ $request->id }})">
                                    {{ __('admin.users.reject') }}<span class="visually-hidden"> {{ $request->pseudo }}</span>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endcan

    <section class="card xm-admin-card" aria-labelledby="xmUsersListTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmUsersListTitle">{{ __('admin.users.list_title') }}</h2>
            <div class="d-flex flex-wrap gap-2">
                @can('export', \App\Models\User::class)
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                        <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>{{ __('admin.export.csv_filtered', ['count' => $format->number($rows->total())]) }}
                    </button>
                @endcan
                @can('create', \App\Models\User::class)
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#xmUserCreate"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>{{ __('admin.users.create') }}</button>
                @endcan
            </div>
        </div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmUsersFilters" :active="$this->activeFilterCount()" :placeholder="$seesContacts ? __('admin.users.filters.search_placeholder') : __('admin.users.filters.search_placeholder_moderator')" :search-help="__('admin.users.filters.search_help')">
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="xmUsersRole">{{ __('admin.users.fields.role') }}</label>
                    <select class="form-select form-select-sm" id="xmUsersRole" wire:model.live="role">
                        <option value="">{{ __('admin.users.filters.all_roles') }}</option>
                        @foreach (\App\Application\Admin\Users\AdminRoles::ALL as $roleKey)
                            <option value="{{ $roleKey }}">{{ \App\Application\Admin\Users\AdminRoles::label($roleKey) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="xmUsersStatus">{{ __('admin.users.columns.status') }}</label>
                    <select class="form-select form-select-sm" id="xmUsersStatus" wire:model.live="status">
                        <option value="">{{ __('admin.users.filters.all_statuses') }}</option>
                        @foreach (\App\Enums\UserStatus::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="visually-hidden" for="xmUsersZone">{{ __('admin.users.fields.zone') }}</label>
                    <select class="form-select form-select-sm" id="xmUsersZone" wire:model.live="zone">
                        <option value="">{{ __('admin.users.filters.all_zones') }}</option>
                        @foreach ($this->zones as $z)
                            <option value="{{ $z['id'] }}">{{ $z['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </x-admin.filters>
        </div>

        <x-admin.table id="xmUsersTable" :caption="__('admin.users.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
            ['key' => 'compte', 'label' => __('admin.users.columns.account'), 'sortable' => true],
            ['key' => 'role', 'label' => __('admin.users.columns.role')],
            ['key' => 'statut', 'label' => __('admin.users.columns.status'), 'sortable' => true],
            ['key' => 'zone', 'label' => __('admin.users.columns.zone')],
            ['key' => 'inscrit', 'label' => __('admin.users.columns.joined'), 'sortable' => true],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($rows as $account)
                @php($accountRole = \App\Application\Admin\Users\AdminRoles::of($account))
                <tr wire:key="user-{{ $account->id }}">
                    <td data-label="{{ __('admin.users.columns.account') }}">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <x-avatar :name="$account->pseudo" :src="$account->avatar_url" size="xs" />
                            <div class="min-w-0">
                                <span class="d-block fw-semibold text-break">{{ $account->pseudo }}@if ($me->is($account)) <x-badge color="info" soft>{{ __('admin.users.you') }}</x-badge>@endif</span>
                                @if ($account->name)<span class="d-block small text-secondary text-break">{{ $account->name }}</span>@endif
                                @if ($seesContacts)<span class="d-block small text-secondary text-break">{{ $account->email }}</span>@endif
                            </div>
                        </div>
                    </td>
                    <td data-label="{{ __('admin.users.columns.role') }}"><x-badge :color="\App\Application\Admin\Users\AdminRoles::isAdministration($accountRole) ? 'violet' : 'neutral'" soft>{{ \App\Application\Admin\Users\AdminRoles::label($accountRole) }}</x-badge></td>
                    <td data-label="{{ __('admin.users.columns.status') }}"><x-badge :color="$account->status->badge()" soft>{{ $account->status->label() }}</x-badge></td>
                    <td data-label="{{ __('admin.users.columns.zone') }}"><span class="small">{{ $account->zone?->name ?? '—' }}</span></td>
                    <td data-label="{{ __('admin.users.columns.joined') }}" class="text-nowrap"><time class="small" datetime="{{ $account->created_at?->toIso8601String() }}">{{ $format->shortDate($account->created_at) }}</time></td>
                    <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                        <button type="button" class="btn btn-sm btn-light border" wire:click="open({{ $account->id }})" wire:loading.attr="disabled" wire:target="open({{ $account->id }})">
                            <i class="bi bi-person-gear me-1" aria-hidden="true"></i>{{ __('admin.users.open') }}<span class="visually-hidden"> {{ $account->pseudo }}</span>
                        </button>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-admin.empty icon="people" :filtered="$this->activeFilterCount() > 0" :title="__('admin.users.empty')">{{ __('admin.users.empty_text') }}</x-admin.empty>
            </x-slot:emptyState>
            <x-slot:footer>
                <x-admin.pagination id="xmUsersPager" :paginator="$rows" />
            </x-slot:footer>
        </x-admin.table>
    </section>

    {{-- Matrice des permissions : générée en lecture depuis spatie, jamais éditable (P12-01). --}}
    <section class="card xm-admin-card mt-4" aria-labelledby="xmUsersMatrixTitle">
        <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmUsersMatrixTitle">{{ __('admin.users.permissions_matrix') }}</h2></div>
        <div class="card-body pb-0"><p class="small text-secondary xm-prose">{{ __('admin.users.matrix_help') }}</p></div>
        <div class="xm-table-wrap">
            <table class="table table-sm align-middle text-center mb-0" id="xmUsersMatrix">
                <caption class="visually-hidden">{{ __('admin.users.matrix_caption') }}</caption>
                <thead>
                    <tr>
                        <th scope="col" class="text-start">{{ __('admin.users.module') }}</th>
                        @foreach ($this->matrix['roles'] as $matrixRole)
                            <th scope="col">{{ $matrixRole['label'] }}<span class="d-block small fw-normal text-secondary">{{ trans_choice('admin.users.accounts_count', $matrixRole['accounts'], ['count' => $format->number($matrixRole['accounts'])]) }}</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->matrix['rows'] as $matrixRow)
                        <tr>
                            <th scope="row" class="text-start fw-normal">{{ __('admin.users.modules.'.$matrixRow['key']) }}</th>
                            @foreach ($this->matrix['roles'] as $matrixRole)
                                @php($level = $matrixRow['levels'][$matrixRole['key']])
                                <td>
                                    @switch($level)
                                        @case('full') <span class="text-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin.users.permission_levels.full') }}</span></span> @break
                                        @case('superadmin') <span class="text-primary"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin.users.permission_levels.superadmin') }}</span></span> @break
                                        @case('read') <span class="text-warning-emphasis"><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin.users.permission_levels.read') }}</span></span> @break
                                        @default <span class="text-secondary"><i class="bi bi-x-circle" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin.users.permission_levels.none') }}</span></span>
                                    @endswitch
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-secondary d-flex flex-wrap gap-3">
            <span><i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i> {{ __('admin.users.permission_levels.full') }}</span>
            <span><i class="bi bi-shield-lock-fill text-primary" aria-hidden="true"></i> {{ __('admin.users.permission_levels.superadmin') }}</span>
            <span><i class="bi bi-eye text-warning-emphasis" aria-hidden="true"></i> {{ __('admin.users.permission_levels.read') }}</span>
            <span><i class="bi bi-x-circle" aria-hidden="true"></i> {{ __('admin.users.permission_levels.none') }}</span>
        </div>
    </section>

    {{-- Modale du compte : montée en permanence, vidée à la fermeture. Chaque bouton n'apparaît que si la policy l'autorise. --}}
    <x-admin.modal id="xmUserDetail" size="lg" heading="h2" :title="$this->account ? __('admin.users.detail.title', ['name' => $this->account->pseudo]) : __('admin.users.detail.fallback')"
                   x-data x-on:hidden-bs-modal.dot="$wire.closeDetail()">
        @if ($account = $this->account)
            @php($facts = $this->accountFacts)
            @php($isMe = $me->is($account))
            <div class="d-flex flex-wrap gap-2 mb-3">
                <x-badge :color="$account->status->badge()" soft>{{ $account->status->label() }}</x-badge>
                <x-badge :color="\App\Application\Admin\Users\AdminRoles::isAdministration($facts['role']) ? 'violet' : 'neutral'" soft>{{ \App\Application\Admin\Users\AdminRoles::label($facts['role']) }}</x-badge>
                @if ($facts['warnings'] > 0)<x-badge color="warning" soft icon="exclamation-triangle">{{ trans_choice('admin.users.detail.warnings', $facts['warnings'], ['count' => $facts['warnings']]) }}</x-badge>@endif
            </div>
            <dl class="row small mb-3">
                @if ($seesContacts)
                    <dt class="col-sm-4">{{ __('admin.users.fields.name') }}</dt><dd class="col-sm-8">{{ $account->name ?: '—' }}</dd>
                    <dt class="col-sm-4">{{ __('admin.users.fields.email') }}</dt><dd class="col-sm-8 text-break">{{ $account->email }}</dd>
                    <dt class="col-sm-4">{{ __('admin.users.fields.phone') }}</dt><dd class="col-sm-8">{{ $account->phone ?: '—' }}</dd>
                @endif
                <dt class="col-sm-4">{{ __('admin.users.fields.zone') }}</dt><dd class="col-sm-8">{{ $account->zone?->name ?? '—' }}</dd>
                <dt class="col-sm-4">{{ __('admin.users.columns.joined') }}</dt><dd class="col-sm-8">{{ $format->longDate($account->created_at) }}</dd>
                <dt class="col-sm-4">{{ __('admin.users.columns.last_seen') }}</dt><dd class="col-sm-8">{{ $account->last_seen_at ? $format->ago($account->last_seen_at) : '—' }}</dd>
                <dt class="col-sm-4">{{ __('admin.users.detail.reports') }}</dt><dd class="col-sm-8">{{ $format->number($facts['reports']) }}</dd>
                @if ($account->suspension_reason && $account->status === \App\Enums\UserStatus::Suspended)
                    <dt class="col-sm-4">{{ __('admin.users.detail.suspension') }}</dt><dd class="col-sm-8 text-break">{{ $account->suspension_reason }}@if ($account->suspended_at) <span class="text-secondary">· {{ $format->shortDate($account->suspended_at) }}</span>@endif</dd>
                @endif
            </dl>

            @if ($isMe)
                <x-alert type="info" class="small">{{ __('admin.users.detail.self') }}</x-alert>
            @endif

            @can('validatePeer', $account)
                @if ($account->status === \App\Enums\UserStatus::Pending)
                    <div class="border rounded p-3 mb-3 bg-warning-subtle d-flex flex-wrap align-items-center gap-2">
                        <span class="flex-grow-1 small">{{ __('admin.users.detail.pending_help') }}</span>
                        <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $account->id }})" wire:loading.attr="disabled"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin.users.validate') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reject({{ $account->id }})" wire:confirm="{{ __('admin.users.confirm.reject', ['name' => $account->pseudo]) }}" wire:loading.attr="disabled">{{ __('admin.users.reject') }}</button>
                    </div>
                @endif
            @endcan

            @if (! $isMe && $assignable !== [])
                @can('assignRole', $account)
                    <form class="border rounded p-3 mb-3" wire:submit="changeRole" novalidate>
                        <label class="form-label small fw-semibold" for="xmUserRole">{{ __('admin.users.role_of', ['name' => $account->pseudo]) }}</label>
                        <div class="input-group input-group-sm">
                            <select class="form-select" id="xmUserRole" wire:model="roleChoice" aria-describedby="xmUserRoleHelp">
                                @foreach (\App\Application\Admin\Users\AdminRoles::ALL as $roleKey)
                                    <option value="{{ $roleKey }}" @disabled(! in_array($roleKey, $assignable, true))>{{ \App\Application\Admin\Users\AdminRoles::label($roleKey) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="changeRole">{{ __('admin.users.save_role') }}</button>
                        </div>
                        <div class="form-text" id="xmUserRoleHelp">{{ __('admin.users.role_help') }}</div>
                    </form>
                @endcan
            @elseif (! $isMe)
                <p class="small text-secondary"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ __('admin.users.detail.role_locked') }}</p>
            @endif

            @if (! $isMe && $account->status === \App\Enums\UserStatus::Active)
                @can('suspend', $account)
                    <form class="border rounded p-3 mb-3" wire:submit="suspend" novalidate>
                        <label class="form-label small fw-semibold" for="xmUserSuspendReason">{{ __('admin.users.suspend_reason') }}</label>
                        <textarea class="form-control form-control-sm @error('suspendReason') is-invalid @enderror" id="xmUserSuspendReason" rows="2" maxlength="{{ \App\Application\Admin\Users\AccountSuspension::REASON_MAX }}" wire:model="suspendReason"
                                  aria-describedby="xmUserSuspendHelp @error('suspendReason') xmUserSuspendError @enderror" @error('suspendReason') aria-invalid="true" @enderror></textarea>
                        <div class="form-text" id="xmUserSuspendHelp">{{ __('admin.users.suspend_help') }}</div>
                        @error('suspendReason')<div class="invalid-feedback d-block" id="xmUserSuspendError">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-sm btn-outline-danger mt-2" wire:loading.attr="disabled" wire:target="suspend"><i class="bi bi-pause-circle me-1" aria-hidden="true"></i>{{ __('admin.users.suspend') }}</button>
                    </form>
                @endcan
            @endif

            <div class="d-flex flex-wrap gap-2">
                @if ($account->status === \App\Enums\UserStatus::Suspended)
                    @can('users.manage')
                        @can('reactivate', $account)
                            <button type="button" class="btn btn-sm btn-success" wire:click="reactivate" wire:confirm="{{ __('admin.users.confirm.reactivate', ['name' => $account->pseudo]) }}" wire:loading.attr="disabled"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('admin.users.reactivate') }}</button>
                        @endcan
                    @endcan
                @endif
                @can('resetPassword', $account)
                    <button type="button" class="btn btn-sm btn-light border" wire:click="resetPassword" wire:confirm="{{ __('admin.users.confirm.reset', ['name' => $account->pseudo]) }}" wire:loading.attr="disabled"><i class="bi bi-key me-1" aria-hidden="true"></i>{{ __('admin.users.reset_password') }}</button>
                @endcan
                @if (! $isMe)
                    @can('delete', $account)
                        <button type="button" class="btn btn-sm btn-light border text-danger" wire:click="delete" wire:confirm="{{ __('admin.users.confirm.delete', ['name' => $account->pseudo]) }}" wire:loading.attr="disabled"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin.users.delete') }}</button>
                    @endcan
                @endif
            </div>
        @endif
    </x-admin.modal>

    {{-- Création d'un compte : jamais de mot de passe saisi, la personne le choisit par l'e-mail de réinitialisation. --}}
    @can('create', \App\Models\User::class)
        <x-admin.modal id="xmUserCreate" size="lg" heading="h2" :title="__('admin.users.create_title')" dirty-guard>
            <form id="xmUserCreateForm" wire:submit="create" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <x-field name="newName" :label="__('admin.users.fields.name')" wire:model.blur="newName" :error="$errors->first('newName')" autocomplete="off" maxlength="120" />
                    </div>
                    <div class="col-md-6">
                        <x-field name="newPseudo" :label="__('admin.users.fields.pseudo')" wire:model.blur="newPseudo" :error="$errors->first('newPseudo')" autocomplete="off" :hint="__('admin.users.fields.pseudo_help')" />
                    </div>
                    <div class="col-md-6">
                        <x-field name="newEmail" type="email" :label="__('admin.users.fields.email')" wire:model.blur="newEmail" :error="$errors->first('newEmail')" autocomplete="off" maxlength="190" />
                    </div>
                    <div class="col-md-6">
                        <x-field name="newPhone" type="tel" :label="__('admin.users.fields.phone')" wire:model.blur="newPhone" :error="$errors->first('newPhone')" autocomplete="off" optional />
                    </div>
                    <div class="col-md-6">
                        <x-field name="newZone" type="select" :label="__('admin.users.fields.zone')" wire:model.blur="newZone" :error="$errors->first('newZone')" optional
                                 :options="['' => __('admin.users.filters.all_zones')] + collect($this->zones)->pluck('name', 'id')->all()" />
                    </div>
                    <div class="col-md-6">
                        <x-field name="newRole" type="select" :label="__('admin.users.fields.role')" wire:model.blur="newRole" :error="$errors->first('newRole')"
                                 :options="collect($assignable)->mapWithKeys(fn ($r) => [$r => \App\Application\Admin\Users\AdminRoles::label($r)])->all()" />
                    </div>
                    <div class="col-12">
                        <x-field name="newInvite" type="switch" :label="__('admin.users.fields.send_invitation')" wire:model="newInvite" :hint="__('admin.users.fields.send_invitation_help')" />
                    </div>
                </div>
            </form>
            <x-slot:footer>
                <button type="submit" form="xmUserCreateForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="create"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>{{ __('admin.users.create_action') }}</button>
            </x-slot:footer>
        </x-admin.modal>
    @endcan
</div>
