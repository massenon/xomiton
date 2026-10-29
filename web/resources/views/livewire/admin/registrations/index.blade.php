{{--
    Inscriptions (P12-02) : composant App\Livewire\Admin\Registrations\Index, gabarit admin.
    Ordre : introduction · KPI · état + interrupteur · options · inscriptions par semaine · demandes en attente · dix dernières.
    L'état annoncé est celui que voit la page publique à cet instant (interrupteur ET plage horaire).
--}}
<div class="xm-registrations">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin.registrations.intro') }}</p>

    <x-admin.kpi id="xmRegKpis" :items="[
        ['label' => __('admin.registrations.kpi.state'), 'value' => $this->state['effective'] ? __('admin.registrations.open') : __('admin.registrations.closed'), 'icon' => $this->state['effective'] ? 'door-open' : 'door-closed', 'variant' => $this->state['effective'] ? 'success' : 'danger'],
        ['label' => __('admin.registrations.kpi.week'), 'value' => $format->number($this->kpis['week']), 'icon' => 'person-plus'],
        ['label' => __('admin.registrations.kpi.pending'), 'value' => $format->number($this->kpis['pending']), 'icon' => 'hourglass-split', 'variant' => 'warning'],
        ['label' => __('admin.registrations.kpi.total'), 'value' => $format->number($this->kpis['total']), 'icon' => 'people', 'variant' => 'violet'],
    ]" />

    <div class="row g-4">
        <div class="col-lg-7">
            <section @class(['card xm-admin-card mb-4', 'border-success' => $this->state['effective'], 'border-danger' => ! $this->state['effective']]) aria-labelledby="xmRegStateTitle">
                <div class="card-body p-4 d-flex flex-wrap align-items-center gap-3">
                    <div class="flex-grow-1">
                        <h2 class="h4 fw-bold mb-1" id="xmRegStateTitle">
                            <i @class(['bi me-2', 'bi-door-open text-success' => $this->state['effective'], 'bi-door-closed text-danger' => ! $this->state['effective']]) aria-hidden="true"></i>{{ $this->state['effective'] ? __('admin.registrations.open') : __('admin.registrations.closed') }}
                        </h2>
                        <p class="text-secondary mb-0">
                            @if ($this->state['open'] && ! $this->state['effective'])
                                {{ __('admin.registrations.state.outside_window', ['start' => $windowStart, 'end' => $windowEnd]) }}
                            @elseif ($this->state['effective'])
                                {{ $manual === 'all' ? __('admin.registrations.state.open_all') : __('admin.registrations.state.open_peers') }}
                            @else
                                {{ __('admin.registrations.state.closed') }}
                            @endif
                        </p>
                    </div>
                    @if ($this->state['open'])
                        <button type="button" class="btn btn-outline-danger" wire:click="setOpen(false)" wire:confirm="{{ __('admin.registrations.confirm.close') }}" wire:loading.attr="disabled" wire:target="setOpen"><i class="bi bi-door-closed me-1" aria-hidden="true"></i>{{ __('admin.registrations.close_action') }}</button>
                    @else
                        <button type="button" class="btn btn-success" wire:click="setOpen(true)" wire:loading.attr="disabled" wire:target="setOpen"><i class="bi bi-door-open me-1" aria-hidden="true"></i>{{ __('admin.registrations.open_action') }}</button>
                    @endif
                </div>
                <div class="card-footer small d-flex flex-wrap gap-3 justify-content-between">
                    <span><i class="bi bi-lightning-charge me-1" aria-hidden="true"></i>{{ __('admin.registrations.immediate') }}</span>
                    <a href="{{ route('inscription') }}" target="_blank" rel="noopener noreferrer">{{ __('admin.registrations.preview') }}<i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i><span class="visually-hidden"> {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                </div>
            </section>

            <form class="card xm-admin-card mb-4" wire:submit="save" novalidate aria-labelledby="xmRegOptionsTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmRegOptionsTitle">{{ __('admin.registrations.options_title') }}</h2></div>
                <div class="card-body row g-3">
                    <fieldset class="col-12" aria-describedby="xmRegManualHelp">
                        <legend class="form-label fw-semibold fs-6">{{ __('admin.registrations.manual.label') }}</legend>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="xmRegManualPeers" value="peers" wire:model="manual">
                            <label class="form-check-label" for="xmRegManualPeers">{{ __('admin.registrations.manual.peers') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="xmRegManualAll" value="all" wire:model="manual">
                            <label class="form-check-label" for="xmRegManualAll">{{ __('admin.registrations.manual.all') }}</label>
                        </div>
                        <div class="form-text" id="xmRegManualHelp">{{ __('admin.registrations.manual.help') }}</div>
                        @error('manual')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </fieldset>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="xmRegMessage">{{ __('admin.registrations.closed_message') }}</label>
                        <textarea class="form-control @error('message') is-invalid @enderror" id="xmRegMessage" rows="3" maxlength="{{ $messageMax }}" wire:model.blur="message"
                                  aria-describedby="xmRegMessageHelp @error('message') xmRegMessageError @enderror" @error('message') aria-invalid="true" @enderror></textarea>
                        <div class="d-flex justify-content-between form-text" id="xmRegMessageHelp"><span>{{ __('admin.registrations.closed_message_help') }}</span><span>{{ __('admin.registrations.counter', ['count' => mb_strlen($message), 'max' => $messageMax]) }}</span></div>
                        @error('message')<div class="invalid-feedback d-block" id="xmRegMessageError">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="xmRegReopen">{{ __('admin.registrations.reopen_at') }}</label>
                        <input class="form-control @error('reopenAt') is-invalid @enderror" type="datetime-local" id="xmRegReopen" wire:model.blur="reopenAt" aria-describedby="xmRegReopenHelp" @error('reopenAt') aria-invalid="true" @enderror>
                        <div class="form-text" id="xmRegReopenHelp">{{ __('admin.registrations.reopen_help') }}</div>
                        @error('reopenAt')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="xmRegWindowOn" wire:model.live="windowOn">
                            <label class="form-check-label fw-semibold" for="xmRegWindowOn">{{ __('admin.registrations.window_on') }}</label>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small" for="xmRegWindowStart">{{ __('admin.registrations.window_start') }}</label>
                                <input class="form-control form-control-sm @error('windowStart') is-invalid @enderror" type="time" id="xmRegWindowStart" wire:model.blur="windowStart" @disabled(! $windowOn)>
                                @error('windowStart')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label small" for="xmRegWindowEnd">{{ __('admin.registrations.window_end') }}</label>
                                <input class="form-control form-control-sm @error('windowEnd') is-invalid @enderror" type="time" id="xmRegWindowEnd" wire:model.blur="windowEnd" @disabled(! $windowOn)>
                                @error('windowEnd')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-text">{{ __('admin.registrations.window_help') }}</div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin.registrations.save') }}</button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <section class="card xm-admin-card mb-4" aria-labelledby="xmRegWeeksTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmRegWeeksTitle">{{ __('admin.registrations.weeks_title') }}</h2></div>
                <div class="card-body">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="visually-hidden">{{ __('admin.registrations.weeks_caption') }}</caption>
                        <thead class="visually-hidden"><tr><th scope="col">{{ __('admin.registrations.week_of') }}</th><th scope="col">{{ __('admin.registrations.signups') }}</th></tr></thead>
                        <tbody>
                            @foreach ($this->weeks as $week)
                                <tr>
                                    <th scope="row" class="fw-normal small text-nowrap">{{ __('admin.registrations.week_label', ['date' => $week['label']]) }}</th>
                                    <td class="w-100">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:.6rem" aria-hidden="true"><div class="progress-bar" style="width: {{ $week['share'] }}%"></div></div>
                                            <span class="small fw-semibold text-nowrap">{{ $format->number($week['count']) }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <section class="card xm-admin-card mb-4" aria-labelledby="xmRegPendingTitle">
        <div class="card-header bg-warning-subtle"><h2 class="h6 fw-bold mb-0" id="xmRegPendingTitle"><i class="bi bi-hourglass-split me-2" aria-hidden="true"></i>{{ __('admin.registrations.pending') }}</h2></div>
        @if ($this->pending->isEmpty())
            <div class="card-body"><x-admin.empty icon="check2-circle" :title="__('admin.registrations.empty')">{{ __('admin.registrations.empty_text') }}</x-admin.empty></div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($this->pending as $request)
                    <li class="list-group-item d-flex flex-wrap align-items-center gap-3" wire:key="reg-pending-{{ $request->id }}">
                        <x-avatar :name="$request->pseudo" :src="$request->avatar_url" size="sm" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-break">{{ $request->pseudo }}@if ($request->name) <span class="text-secondary fw-normal">· {{ $request->name }}</span>@endif</div>
                            <div class="small text-secondary">{{ $request->email }}@if ($request->zone) · {{ $request->zone->name }}@endif · {{ __('admin.users.columns.requested_role', ['role' => $request->profile?->member_type?->label() ?? __('admin.users.roles.membre')]) }} · {{ $format->ago($request->created_at) }}</div>
                            @if ($flags = \App\Application\Auth\Support\RegistrationFlags::parse($request->registration_flags))
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    @foreach ($flags as $flag)
                                        <x-badge color="warning" soft icon="flag-fill">{{ __('admin.registrations.flags.'.$flag) }}</x-badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @if ($canValidate)
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $request->id }})" wire:loading.attr="disabled" wire:target="approve({{ $request->id }})"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin.registrations.approve') }}<span class="visually-hidden"> {{ $request->pseudo }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reject({{ $request->id }})" wire:confirm="{{ __('admin.registrations.confirm.reject', ['name' => $request->pseudo]) }}" wire:loading.attr="disabled" wire:target="reject({{ $request->id }})">{{ __('admin.registrations.reject') }}<span class="visually-hidden"> {{ $request->pseudo }}</span></button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="card-footer small text-secondary">
                @if ($canValidate)
                    <label class="form-label small fw-semibold" for="xmRegRejectReason">{{ __('admin.registrations.reject_reason') }}</label>
                    <input type="text" class="form-control form-control-sm mb-2" id="xmRegRejectReason" wire:model="rejectReason" maxlength="200" autocomplete="off">
                @endif
                {{ __('admin.registrations.reject_help') }}
            </div>
        @endif
    </section>

    @if ($canAssignPeer)
        <section class="card xm-admin-card mb-4" aria-labelledby="xmRoleReqTitle">
            <div class="card-header bg-info-subtle"><h2 class="h6 fw-bold mb-0" id="xmRoleReqTitle"><i class="bi bi-person-plus-fill me-2" aria-hidden="true"></i>{{ __('admin.registrations.role_requests.title') }}</h2></div>
            @if ($this->roleRequests->isEmpty())
                <div class="card-body"><x-admin.empty icon="check2-circle" :title="__('admin.registrations.role_requests.empty')">{{ __('admin.registrations.role_requests.empty_text') }}</x-admin.empty></div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($this->roleRequests as $roleRequest)
                        <li class="list-group-item d-flex flex-wrap align-items-center gap-3" wire:key="role-request-{{ $roleRequest->id }}">
                            <x-avatar :name="$roleRequest->user->pseudo" :src="$roleRequest->user->avatar_url" size="sm" />
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-break">{{ $roleRequest->user->pseudo }}</div>
                                <div class="small text-secondary">@if ($roleRequest->user->zone){{ $roleRequest->user->zone->name }} · @endif{{ __('admin.registrations.role_requests.asks', ['role' => __('admin.users.roles.pair')]) }} · {{ $format->ago($roleRequest->created_at) }}</div>
                                @if ($roleRequest->reason)<div class="small text-break mt-1">« {{ $roleRequest->reason }} »</div>@endif
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-success" wire:click="approveRoleRequest({{ $roleRequest->id }})" wire:loading.attr="disabled" wire:target="approveRoleRequest({{ $roleRequest->id }})"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin.registrations.approve') }}<span class="visually-hidden"> {{ $roleRequest->user->pseudo }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="rejectRoleRequest({{ $roleRequest->id }})" wire:confirm="{{ __('admin.registrations.role_requests.confirm_reject', ['name' => $roleRequest->user->pseudo]) }}" wire:loading.attr="disabled" wire:target="rejectRoleRequest({{ $roleRequest->id }})">{{ __('admin.registrations.role_requests.refuse') }}<span class="visually-hidden"> {{ $roleRequest->user->pseudo }}</span></button>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="card-footer small text-secondary">
                    <label class="form-label small fw-semibold" for="xmRoleRejectReason">{{ __('admin.registrations.role_requests.reason') }}</label>
                    <input type="text" class="form-control form-control-sm mb-2" id="xmRoleRejectReason" wire:model="roleRejectReason" minlength="3" maxlength="200" autocomplete="off">
                    {{ __('admin.registrations.role_requests.help') }}
                </div>
            @endif
        </section>
    @endif

    @if ($canInvite)
        <section class="card xm-admin-card mb-4" aria-labelledby="xmRegInviteTitle">
            <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmRegInviteTitle"><i class="bi bi-envelope-heart me-2" aria-hidden="true"></i>{{ __('admin.registrations.invitations.title') }}</h2></div>
            <div class="card-body">
                <p class="small text-secondary">{{ __('admin.registrations.invitations.help') }}</p>
                <form class="row g-2 align-items-end" wire:submit="invite" novalidate>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold" for="xmInviteRole">{{ __('admin.registrations.invitations.role') }}</label>
                        <select class="form-select form-select-sm" id="xmInviteRole" wire:model="inviteRole">
                            <option value="pair">{{ __('admin.users.roles.pair') }}</option>
                            @if ($canInviteAdmin)
                                @foreach (['moderateur', 'admin', 'superadmin'] as $teamRole)
                                    <option value="{{ $teamRole }}">{{ __('admin.users.roles.'.$teamRole) }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold" for="xmInviteEmail">{{ __('admin.registrations.invitations.email') }}</label>
                        <input type="email" class="form-control form-control-sm @error('inviteEmail') is-invalid @enderror" id="xmInviteEmail" wire:model="inviteEmail" maxlength="190" autocomplete="off">
                        @error('inviteEmail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold" for="xmInviteNote">{{ __('admin.registrations.invitations.note') }}</label>
                        <input type="text" class="form-control form-control-sm" id="xmInviteNote" wire:model="inviteNote" maxlength="190" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold" for="xmInviteZone">{{ __('admin.registrations.invitations.zone') }}</label>
                        <select class="form-select form-select-sm @error('inviteZone') is-invalid @enderror" id="xmInviteZone" wire:model="inviteZone">
                            <option value="">{{ __('admin.registrations.invitations.zone_none') }}</option>
                            @foreach ($inviteZones as $zone)
                                <option value="{{ $zone['id'] }}">{{ $zone['name'] }}</option>
                            @endforeach
                        </select>
                        @error('inviteZone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold" for="xmInviteDays">{{ __('admin.registrations.invitations.days') }}</label>
                        <input type="number" class="form-control form-control-sm @error('inviteDays') is-invalid @enderror" id="xmInviteDays" wire:model="inviteDays" min="{{ $inviteMinDays }}" max="{{ $inviteMaxDays }}" inputmode="numeric">
                        @error('inviteDays')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-sm btn-primary" wire:loading.attr="disabled" wire:target="invite">{{ __('admin.registrations.invitations.create') }}</button>
                    </div>
                </form>

                @if ($createdInvite)
                    <div class="alert alert-success mt-3 mb-0" role="status">
                        <div class="fw-semibold">{{ __('admin.registrations.invitations.once') }}</div>
                        <div class="small">{{ __('admin.registrations.invitations.code') }} <code>{{ $createdInvite['code'] }}</code></div>
                        <div class="small text-break">{{ __('admin.registrations.invitations.link') }} <code class="user-select-all">{{ $createdInvite['link'] }}</code></div>
                    </div>
                @endif
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($this->invitationList as $invitation)
                    <li class="list-group-item d-flex flex-wrap align-items-center gap-2" wire:key="reg-invite-{{ $invitation->id }}">
                        <span class="fw-semibold">{{ __('admin.users.roles.'.$invitation->role) }}</span>
                        <code class="small">…{{ $invitation->code_hint }}</code>
                        <x-badge :color="match ($invitation->status) { 'accepted' => 'success', 'redeemed' => 'info', 'pending' => 'primary', default => 'secondary' }" soft>{{ __('admin.registrations.invitations.status.'.$invitation->status) }}</x-badge>
                        <span class="small text-secondary">{{ $invitation->email }}@if ($invitation->zone) · {{ $invitation->zone->name }}@endif @if ($invitation->user) · {{ $invitation->user->pseudo }}@endif · {{ __('admin.registrations.invitations.expires', ['date' => $format->shortDate($invitation->expires_at)]) }}</span>
                        @if (in_array($invitation->status, ['pending', 'redeemed'], true))
                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" wire:click="revokeInvitation({{ $invitation->id }})" wire:confirm="{{ __('admin.registrations.invitations.confirm_revoke') }}" wire:loading.attr="disabled" wire:target="revokeInvitation({{ $invitation->id }})">{{ __('admin.registrations.invitations.revoke') }}</button>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-secondary">{{ __('admin.registrations.invitations.empty') }}</li>
                @endforelse
            </ul>
        </section>
    @endif

    <section class="card xm-admin-card" aria-labelledby="xmRegRecentTitle">
        <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmRegRecentTitle">{{ __('admin.registrations.recent') }}</h2></div>
        <ul class="list-group list-group-flush">
            @forelse ($this->recent as $account)
                <li class="list-group-item d-flex flex-wrap align-items-center gap-2" wire:key="reg-recent-{{ $account->id }}">
                    <span class="fw-semibold text-break">{{ $account->pseudo }}</span>
                    <x-badge :color="$account->status->badge()" soft>{{ $account->status->label() }}</x-badge>
                    <span class="small text-secondary ms-auto">{{ $account->zone?->name }}@if ($account->zone) · @endif{{ $format->tableDateTime($account->created_at) }}</span>
                </li>
            @empty
                <li class="list-group-item text-secondary">{{ __('admin.registrations.recent_empty') }}</li>
            @endforelse
        </ul>
    </section>
</div>
