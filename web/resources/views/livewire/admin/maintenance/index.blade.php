{{--
    Écran « Mode maintenance » (P13-06) : composant App\Livewire\Admin\Maintenance\Index, gabarit admin.
    Ordre : introduction + « Voir comme un visiteur » · bandeau d'état (badge, phrase, compte à rebours, action
    principale) · KPI · colonne gauche (message public, planification, fenêtres planifiées, historique) · colonne droite
    (aperçu en direct de la page publique, liste blanche RG-01 en lecture seule, garde-fous) · modale rouge d'activation
    (toujours montée, wire:ignore.self). Styles : resources/css/app/pages/admin-maintenance.css (jetons --xm-*).
--}}
@php
    // Heures dans le fuseau d'affichage (Bénin), comme la page publique et les champs de planification.
    $format = \App\Application\Platform\MaintenancePage::formatter();
    $state = $overview->state;
    $endsAt = $snapshot->endsAt;
    $next = $overview->upcoming->first();
    $historyItems = $overview->history->map(function (\App\Models\AuditLog $log) use ($format): array {
        $detail = \App\Application\Platform\MaintenanceOverview::detailOf($log);
        $colors = ['maintenance.enabled' => 'danger', 'maintenance.disabled' => 'success', 'maintenance.scheduled' => 'primary', 'maintenance.window_cancelled' => 'warning', 'maintenance.message_updated' => 'info'];
        $text = $detail['reason'] !== null ? __('maintenance.admin.history.reason', ['reason' => $detail['reason']]) : ($log->action === 'maintenance.message_updated' ? ($detail['message'] ?? __('maintenance.default_message')) : __('maintenance.admin.history.no_reason'));

        return [
            'title' => __('maintenance.admin.history.actions.'.\Illuminate\Support\Str::after($log->action, 'maintenance.')),
            'date' => $format->tableDateTime($log->created_at),
            'datetime' => $log->created_at?->toIso8601String(),
            'by' => $log->user?->pseudo ?? $log->actorLabel(),
            'color' => $colors[$log->action] ?? 'primary',
            'text' => $text,
        ];
    })->all();
    $remaining = $endsAt ? max(0, (int) now()->diffInSeconds($endsAt, false)) : null;
    $remainingText = $remaining === null ? null : ($remaining >= 3600 ? sprintf('%02d:', intdiv($remaining, 3600)) : '').sprintf('%02d:%02d', intdiv($remaining % 3600, 60), $remaining % 60);
@endphp
<div class="xm-maint" wire:poll.60s>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <p class="text-secondary mb-0 xm-maint__intro">{{ __('maintenance.admin.intro') }}</p>
        <a class="btn btn-light border" href="{{ route('admin.maintenance.preview') }}" target="_blank" rel="noopener"><i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('maintenance.admin.view_as_visitor') }}</a>
    </div>

    {{-- Bandeau d'état --}}
    <section @class(['card xm-maint-state mb-4', 'xm-maint-state--'.$state->value]) aria-labelledby="xmMaintStateTitle" data-state="{{ $state->value }}">
        <div class="card-body">
            <div class="xm-maint-state__icon" aria-hidden="true"><i class="bi bi-{{ $state->icon() }}"></i></div>
            <div class="xm-maint-state__body">
                <h2 class="h3 fw-bold mb-1" id="xmMaintStateTitle">{{ __('maintenance.admin.headlines.'.$state->value) }}</h2>
                <p class="mb-1">{{ __('maintenance.admin.state_text.'.$state->value) }}</p>
                @if ($state === \App\Enums\MaintenanceState::Ongoing && $snapshot->startsAt)
                    <p class="small text-secondary mb-0"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ __('maintenance.admin.kpi.since', ['date' => $format->dateTime($snapshot->startsAt)]) }}</p>
                @elseif ($state === \App\Enums\MaintenanceState::Ended && $overview->endedAt)
                    <p class="small text-secondary mb-0"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ __('maintenance.admin.kpi.ended_at', ['date' => $format->dateTime($overview->endedAt)]) }}</p>
                @elseif ($state === \App\Enums\MaintenanceState::Announced && $overview->announced)
                    <p class="small text-secondary mb-0"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ __('maintenance.admin.kpi.starts_in', ['date' => $format->dateTime($overview->announced->starts_at)]) }}</p>
                @endif
                <p class="small text-secondary mb-0 mt-2">{{ $snapshot->active ? __('maintenance.admin.live.disable_text') : __('maintenance.admin.live.text') }}</p>
            </div>
            <div class="xm-maint-state__side">
                @if ($state === \App\Enums\MaintenanceState::Ongoing)
                    <div class="xm-maint-state__clock" wire:key="clock-{{ $endsAt?->getTimestamp() ?? 'none' }}">
                        <div class="small text-secondary">{{ $endsAt ? __('maintenance.admin.kpi.remaining') : __('maintenance.admin.kpi.ends') }}</div>
                        @if ($endsAt)
                            <div class="xm-maint-state__digits" wire:ignore
                                 x-data="{ ends: {{ $endsAt->getTimestamp() * 1000 }}, left: @js($remainingText), tick() { const s = Math.max(0, Math.round((this.ends - Date.now()) / 1000)); const p = (n) => String(n).padStart(2, '0'); this.left = (s >= 3600 ? p(Math.floor(s / 3600)) + ':' : '') + p(Math.floor((s % 3600) / 60)) + ':' + p(s % 60); } }"
                                 x-init="tick(); setInterval(() => tick(), 1000)" x-text="left" aria-hidden="true">{{ $remainingText }}</div>
                            <div class="small text-secondary">{{ __('maintenance.admin.kpi.ends_on', ['date' => $format->dateTime($endsAt)]) }}</div>
                        @else
                            <div class="xm-maint-state__digits">{{ __('maintenance.admin.kpi.unknown') }}</div>
                        @endif
                    </div>
                @endif
                <div class="xm-maint-state__actions">
                    @if ($snapshot->active)
                        <button type="button" class="btn btn-success btn-lg" wire:click="disable" wire:loading.attr="disabled" wire:target="disable">
                            <span wire:loading.remove wire:target="disable"><i class="bi bi-unlock me-1" aria-hidden="true"></i></span><span class="spinner-border spinner-border-sm me-1" wire:loading wire:target="disable" aria-hidden="true"></span>{{ __('maintenance.admin.live.disable') }}
                        </button>
                    @else
                        <button type="button" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#xmMaintEnable"><i class="bi bi-cone-striped me-1" aria-hidden="true"></i>{{ __('maintenance.admin.live.enable') }}</button>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Indicateurs (x-admin.kpi, P10-02 : accordéon « Indicateurs clés » < 768 px) --}}
    <x-admin.kpi id="xmMaintKpis" :items="[
        ['label' => __('maintenance.admin.kpi.state'), 'value' => $state->label(), 'variant' => ['inactive' => 'success', 'announced' => 'warning', 'ongoing' => 'danger', 'ended' => ''][$state->value], 'icon' => $state->icon()],
        ['label' => __('maintenance.admin.kpi.ends'), 'value' => $snapshot->active ? ($endsAt ? $format->time($endsAt) : __('maintenance.admin.kpi.unknown')) : '—', 'icon' => 'clock-history', 'sub' => $snapshot->active && $endsAt ? \App\Application\Platform\MaintenancePage::day($endsAt) : null],
        ['label' => __('maintenance.admin.kpi.next'), 'value' => $next ? $format->dayMonth($next->starts_at) : __('maintenance.admin.kpi.none'), 'icon' => 'calendar-event', 'sub' => $next ? (\App\Application\Platform\MaintenancePage::local($next->starts_at)->isSameDay(\App\Application\Platform\MaintenancePage::local($next->ends_at)) ? __('maintenance.admin.kpi.hours', ['from' => $format->time($next->starts_at), 'to' => $format->time($next->ends_at)]) : \App\Application\Platform\MaintenancePage::range($next->starts_at, $next->ends_at)) : null],
        ['label' => __('maintenance.admin.schedule.list_title'), 'value' => $format->number($overview->upcoming->count()), 'icon' => 'list-check', 'sub' => trans_choice('maintenance.admin.kpi.announced_count', $overview->upcoming->filter(fn (\App\Models\MaintenanceWindow $w): bool => $w->isAnnounced())->count())],
    ]" cols="col-sm-6 col-xl-3" />

    <div class="row g-4">
        <div class="col-xl-7 d-flex flex-column gap-4">
            {{-- Message public --}}
            <x-card :title="__('maintenance.admin.message.title')" header>
                <form wire:submit="saveMessage">
                    <x-field type="textarea" name="message" :label="__('maintenance.admin.fields.message_editor')" :hint="__('maintenance.admin.fields.message_hint')" :maxlength="\App\Application\Platform\MaintenanceStatus::MESSAGE_MAX_LENGTH" :value="$message" rows="3" wire:model.live.debounce.400ms="message" />
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveMessage"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ __('maintenance.admin.message.save') }}</button>
                        <span class="small text-secondary">{{ __('maintenance.admin.message.preview_hint') }}</span>
                    </div>
                </form>
            </x-card>

            {{-- Planification --}}
            <x-card :title="__('maintenance.admin.schedule.title')" header>
                <p class="text-secondary small">{{ __('maintenance.admin.schedule.text') }}</p>
                <form wire:submit="schedule" novalidate>
                    @error('maintenance')<x-alert type="danger">{{ $message }}</x-alert>@enderror
                    <div class="row gx-3">
                        <div class="col-md-6"><x-field type="datetime-local" name="startsAt" :label="__('maintenance.admin.fields.starts_at')" :hint="$timezoneHint" :value="$startsAt" wire:model="startsAt" /></div>
                        <div class="col-md-6"><x-field type="datetime-local" name="endsAt" :label="__('maintenance.admin.fields.ends_at')" :hint="$timezoneHint" :value="$endsAt" wire:model="endsAt" /></div>
                        <div class="col-md-6"><x-field type="select" name="announceHours" :label="__('maintenance.admin.fields.announce_hours')" :hint="__('maintenance.admin.fields.announce_hint')" :options="$announceOptions" :value="$announceHours" wire:model="announceHours" /></div>
                        <div class="col-md-6"><x-field name="windowReason" :label="__('maintenance.admin.fields.window_reason')" :hint="__('maintenance.admin.fields.reason_hint')" maxlength="255" :value="$windowReason" wire:model="windowReason" /></div>
                        <div class="col-12"><x-field type="textarea" name="windowMessage" :label="__('maintenance.admin.fields.window_message')" :hint="__('maintenance.admin.fields.message_hint')" :maxlength="\App\Application\Platform\MaintenanceStatus::MESSAGE_MAX_LENGTH" :value="$windowMessage" rows="2" optional wire:model="windowMessage" /></div>
                    </div>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="schedule"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>{{ __('maintenance.admin.schedule.submit') }}</button>
                </form>
            </x-card>

            {{-- Fenêtres planifiées --}}
            <x-card :title="__('maintenance.admin.schedule.list_title')" header>
                @if ($overview->upcoming->isEmpty())
                    <x-empty-state icon="calendar-x" compact :title="__('maintenance.admin.schedule.empty')" />
                @else
                    <ul class="list-unstyled mb-0 xm-maint-windows">
                        @foreach ($overview->upcoming as $window)
                            <li class="xm-maint-window" wire:key="window-{{ $window->id }}">
                                <div class="xm-maint-window__date" aria-hidden="true">
                                    <span class="xm-maint-window__day">{{ \App\Application\Platform\MaintenancePage::local($window->starts_at)->day }}</span>
                                    <span class="xm-maint-window__month">{{ $format->monthName(\App\Application\Platform\MaintenancePage::local($window->starts_at)->month, true) }}</span>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="fw-semibold">{{ \Illuminate\Support\Str::ucfirst(\App\Application\Platform\MaintenancePage::range($window->starts_at, $window->ends_at)) }}</span>
                                        @if ($window->isAnnounced())
                                            <x-badge color="warning" icon="megaphone">{{ __('maintenance.admin.schedule.announced_badge') }}</x-badge>
                                        @else
                                            <x-badge color="primary" soft icon="calendar-check">{{ __('maintenance.admin.schedule.planned_badge') }}</x-badge>
                                        @endif
                                    </div>
                                    @if ($window->message)<p class="small mb-1 mt-1">{{ $window->message }}</p>@endif
                                    <p class="small text-secondary mb-0">
                                        {{ (int) $window->announce_hours > 0 ? __('maintenance.admin.schedule.announce_from', ['date' => $format->dateTime($window->announcedFrom())]) : __('maintenance.admin.schedule.no_announce') }}
                                        @if ($window->reason) · {{ __('maintenance.admin.history.reason', ['reason' => $window->reason]) }}@endif
                                        @if ($window->creator) · {{ __('maintenance.admin.schedule.by', ['name' => $window->creator->pseudo]) }}@endif
                                    </p>
                                </div>
                                <button type="button" class="btn btn-outline-danger flex-shrink-0" wire:click="cancelWindow({{ $window->id }})" wire:confirm="{{ __('maintenance.admin.schedule.cancel_confirm', ['date' => $format->dateTime($window->starts_at)]) }}"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ __('maintenance.admin.schedule.cancel') }}</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- Historique --}}
            <x-card :title="__('maintenance.admin.history.title')" header>
                <x-timeline :items="$historyItems" :label="__('maintenance.admin.history.title')" :empty="__('maintenance.admin.history.empty')" />
            </x-card>
        </div>

        <div class="col-xl-5 d-flex flex-column gap-4">
            {{-- Aperçu en direct --}}
            <x-card :title="__('maintenance.admin.message.preview')" header>
                <div class="d-flex justify-content-center mb-3">
                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('maintenance.admin.message.preview') }}">
                        <button type="button" @class(['btn', 'btn-primary' => $previewTheme === 'light', 'btn-light border' => $previewTheme !== 'light']) wire:click="setPreviewTheme('light')" aria-pressed="{{ $previewTheme === 'light' ? 'true' : 'false' }}"><i class="bi bi-sun me-1" aria-hidden="true"></i>{{ __('maintenance.admin.message.light') }}</button>
                        <button type="button" @class(['btn', 'btn-primary' => $previewTheme === 'dark', 'btn-light border' => $previewTheme !== 'dark']) wire:click="setPreviewTheme('dark')" aria-pressed="{{ $previewTheme === 'dark' ? 'true' : 'false' }}"><i class="bi bi-moon-stars me-1" aria-hidden="true"></i>{{ __('maintenance.admin.message.dark') }}</button>
                    </div>
                </div>
                <div class="xm-maint-phone" data-xm-maintenance-preview>
                    <div class="xm-maint-phone__notch" aria-hidden="true"></div>
                    <div class="xm-maint-phone__screen" inert>
                        <style>{!! $previewCss !!}</style>
                        @include('maintenance.content', $preview + ['embedded' => true])
                    </div>
                </div>
                <p class="small text-secondary text-center mt-3 mb-0">{{ __('maintenance.admin.message.preview_hint') }}</p>
            </x-card>

            {{-- Liste blanche RG-01 (lecture seule) --}}
            <x-card :title="__('maintenance.admin.whitelist.title')" header>
                <x-slot:actions><x-badge color="neutral" icon="lock-fill">{{ __('maintenance.admin.whitelist.locked') }}</x-badge></x-slot:actions>
                <p class="small text-secondary">{{ __('maintenance.admin.whitelist.text') }}</p>
                @foreach ($whitelist as $group => $items)
                    <h3 class="h6 fw-bold mt-3 mb-2">{{ __('maintenance.admin.whitelist.groups.'.$group) }}</h3>
                    <ul class="list-unstyled mb-0 xm-maint-allow" data-xm-whitelist="{{ $group }}">
                        @foreach ($items as $item)
                            <li>
                                <i class="bi bi-shield-lock-fill text-success" aria-hidden="true"></i>
                                <div>
                                    <span>{{ $item['label'] }}</span>
                                    @if ($item['paths'] !== [])
                                        <span class="d-flex flex-wrap gap-1 mt-1">@foreach ($item['paths'] as $path)<code class="xm-maint-path">/{{ $path }}</code>@endforeach</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </x-card>

            {{-- Garde-fous anti-verrouillage --}}
            <x-card :title="__('maintenance.admin.safeguards.title')" header>
                <ul class="list-unstyled mb-3 xm-maint-allow">
                    <li><i class="bi bi-person-check-fill text-primary" aria-hidden="true"></i><span>{{ __('maintenance.admin.safeguards.session') }}</span></li>
                    <li><i class="bi bi-door-open-fill text-primary" aria-hidden="true"></i><span>{{ __('maintenance.admin.safeguards.login') }}</span></li>
                    <li><i class="bi bi-key-fill text-primary" aria-hidden="true"></i><span>{{ __('maintenance.admin.safeguards.password') }}</span></li>
                </ul>
                <p class="small mb-1">{{ __('maintenance.admin.safeguards.command') }}</p>
                <pre class="xm-maint-cli mb-0"><code>php artisan xomiton:maintenance on --minutes=60 --reason="…"
php artisan xomiton:maintenance off
php artisan xomiton:maintenance status</code></pre>
            </x-card>
        </div>
    </div>

    {{-- Modale rouge d'activation : toujours montée (jamais derrière un @if), conservée par Livewire (wire:ignore.self). --}}
    {{-- Erreur de validation ou refus (`enable` : déjà active, anti-verrouillage) : focus sur le premier champ invalide
         ou sur l'alerte (évènement xm-maint-invalid), le clavier ne retombe jamais sur <body>. --}}
    <x-modal id="xmMaintEnable" type="delete" heading="h2" :title="__('maintenance.admin.confirm.title')" class="xm-maint-modal" static wire:ignore.self>
        <form id="xmMaintEnableForm" wire:submit="enable" novalidate
              x-data x-on:xm-maint-invalid.window="setTimeout(() => $el.querySelector('[data-xm-maint-error], [aria-invalid=&quot;true&quot;]')?.focus(), 60)">
            <p>{{ __('maintenance.admin.confirm.text') }}</p>
            @error('enable')<x-alert type="danger" tabindex="-1" data-xm-maint-error>{{ $message }}</x-alert>@enderror
            <x-field name="reason" :label="__('maintenance.admin.fields.reason')" :hint="__('maintenance.admin.fields.reason_hint')" maxlength="255" :value="$reason" wire:model="reason" />
            <x-field type="select" name="duration" :label="__('maintenance.admin.fields.duration')" :hint="__('maintenance.admin.live.estimate_hint')" :options="$durations" :value="$duration" wire:model.live="duration" />
            <x-field type="textarea" name="message" id="f-modal-message" :label="__('maintenance.admin.fields.message')" :hint="__('maintenance.admin.fields.message_hint')" :maxlength="\App\Application\Platform\MaintenanceStatus::MESSAGE_MAX_LENGTH" :value="$message" rows="3" wire:model.live.debounce.400ms="message" />
            <x-field type="checkbox" name="acknowledge" :label="__('maintenance.admin.confirm.acknowledge')" :value="$acknowledge" wire:model="acknowledge" />
        </form>
        <x-slot:footer>
            <button type="submit" form="xmMaintEnableForm" class="btn btn-danger" wire:loading.attr="disabled" wire:target="enable"><i class="bi bi-cone-striped me-1" aria-hidden="true"></i>{{ __('maintenance.admin.confirm.action') }}</button>
        </x-slot:footer>
    </x-modal>
</div>
