{{--
    Paramètres (P13-04) : composant App\Livewire\Admin\Settings\Index, gabarit admin. Onglets (x-admin.tab-nav, ?onglet=) :
    Général · Sécurité · Conservation · Messagerie SMTP (superadmin) · Informations système (version, identité visuelle,
    garde). Un formulaire par onglet, une validation par clé ; lecture seule pour l'admin (`settings.view`).
--}}
@php
    $err = fn (string $key): bool => $errors->has('form.'.$key);
    $msg = fn (string $key): string => (string) $errors->first('form.'.$key);
    $field = fn (string $key): string => 'xmSet'.\Illuminate\Support\Str::studly($key);
@endphp
<div class="xm-settings">
    <p class="text-secondary mb-3 xm-prose">{{ __('admin_system.settings.intro') }}@unless ($canManage) {{ __('admin_system.settings.read_only') }}@endunless</p>

    <x-admin.tab-nav :tabs="$tabList" :current="$tab" :label="__('admin_system.settings.tabs.label')" />

    {{-- Général --}}
    @if ($tab === 'general')
        <form class="card xm-admin-card mb-4" wire:submit="save('general')" novalidate aria-labelledby="xmSetGeneralTitle">
            <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSetGeneralTitle">{{ __('admin_system.settings.general.title') }}</h2></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="{{ $field('site_name') }}">{{ __('admin_system.settings.fields.site_name') }} *</label>
                    <input class="form-control @if ($err('site_name')) is-invalid @endif" id="{{ $field('site_name') }}" wire:model="form.site_name" maxlength="40" required @disabled(! $canManage) @if ($err('site_name')) aria-invalid="true" aria-describedby="{{ $field('site_name') }}Error" @endif>
                    @if ($err('site_name'))<div class="invalid-feedback" id="{{ $field('site_name') }}Error">{{ $msg('site_name') }}</div>@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="{{ $field('contact_email') }}">{{ __('admin_system.settings.fields.contact_email') }} *</label>
                    <input class="form-control @if ($err('contact_email')) is-invalid @endif" type="email" id="{{ $field('contact_email') }}" wire:model="form.contact_email" maxlength="190" required @disabled(! $canManage) @if ($err('contact_email')) aria-invalid="true" aria-describedby="{{ $field('contact_email') }}Error" @endif>
                    @if ($err('contact_email'))<div class="invalid-feedback" id="{{ $field('contact_email') }}Error">{{ $msg('contact_email') }}</div>@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="{{ $field('timezone') }}">{{ __('admin_system.settings.fields.timezone') }}</label>
                    <select class="form-select @if ($err('timezone')) is-invalid @endif" id="{{ $field('timezone') }}" wire:model="form.timezone" @disabled(! $canManage)>
                        @foreach ($timezones as $zone)<option value="{{ $zone }}">{{ __('admin_system.settings.timezones.'.$zone) }}</option>@endforeach
                    </select>
                    <div class="form-text">{{ __('admin_system.settings.general.timezone_hint') }}</div>
                    @if ($err('timezone'))<div class="invalid-feedback d-block">{{ $msg('timezone') }}</div>@endif
                </div>
                <div class="col-md-6">
                    <fieldset @if ($err('languages')) aria-invalid="true" aria-describedby="{{ $field('languages') }}Error" @endif>
                        <legend class="form-label fs-6">{{ __('admin_system.settings.fields.languages') }}</legend>
                        @foreach ($languages as $code)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="{{ $field('languages') }}{{ $code }}" value="{{ $code }}" wire:model="form.languages" @disabled(! $canManage || $code === 'fr')>
                                <label class="form-check-label" for="{{ $field('languages') }}{{ $code }}">{{ __('admin_system.settings.languages.'.$code) }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                    <div class="form-text">{{ __('admin_system.settings.general.languages_hint') }}</div>
                    @if ($err('languages'))<div class="invalid-feedback d-block" id="{{ $field('languages') }}Error">{{ $msg('languages') }}</div>@endif
                </div>
                <div class="col-12"><hr class="my-0"></div>
                @foreach (['anonymous_reports', 'gps_sos', 'offline_sync'] as $flag)
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="{{ $field($flag) }}" wire:model="form.{{ $flag }}" @disabled(! $canManage)>
                            <label class="form-check-label fw-semibold" for="{{ $field($flag) }}">{{ __('admin_system.settings.flags.'.$flag.'.label') }}</label>
                            <div class="small text-secondary">{{ __('admin_system.settings.flags.'.$flag.'.help') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($canManage)<div class="card-footer text-end"><button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="save">{{ __('admin_system.common.save') }}</button></div>@endif
        </form>

        <section class="card xm-admin-card" aria-labelledby="xmSetLinksTitle">
            <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSetLinksTitle">{{ __('admin_system.settings.links.title') }}</h2></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2 align-items-center">
                    <span><i class="bi bi-person-plus me-2" aria-hidden="true"></i>{{ __('admin_system.settings.links.registrations') }} <x-badge :color="$registrationsOpen ? 'success' : 'warning'" soft>{{ $registrationsOpen ? __('admin_system.settings.links.open') : __('admin_system.settings.links.closed') }}</x-badge></span>
                    @can('registrations.manage')<a class="btn btn-sm btn-light border" href="{{ route('admin.inscriptions') }}">{{ __('admin_system.settings.links.manage') }}</a>@endcan
                </li>
                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2 align-items-center">
                    <span><i class="bi bi-cone-striped me-2" aria-hidden="true"></i>{{ __('admin_system.settings.links.maintenance') }} <x-badge :color="$maintenance ? 'danger' : 'neutral'" soft>{{ $maintenance ? __('admin_system.settings.links.active') : __('admin_system.settings.links.inactive') }}</x-badge></span>
                    @can('platform.maintenance')<a class="btn btn-sm btn-light border" href="{{ route('admin.maintenance') }}">{{ __('admin_system.settings.links.manage') }}</a>@endcan
                </li>
            </ul>
        </section>
    @endif

    {{-- Sécurité --}}
    @if ($tab === 'security')
        <form class="card xm-admin-card mb-4" wire:submit="save('security')" novalidate aria-labelledby="xmSetSecurityTitle">
            <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSetSecurityTitle">{{ __('admin_system.settings.security.title') }}</h2></div>
            <div class="card-body row g-3">
                <div class="col-12"><p class="small text-secondary mb-0">{{ __('admin_system.settings.security.floor', ['min' => $floor]) }}</p></div>
                <div class="col-md-4">
                    <label class="form-label" for="{{ $field('password_min_length') }}">{{ __('admin_system.settings.fields.password_min_length') }}</label>
                    <input class="form-control @if ($err('password_min_length')) is-invalid @endif" type="number" inputmode="numeric" min="{{ $floor }}" max="64" id="{{ $field('password_min_length') }}" wire:model="form.password_min_length" @disabled(! $canManage) @if ($err('password_min_length')) aria-invalid="true" aria-describedby="{{ $field('password_min_length') }}Error" @endif>
                    @if ($err('password_min_length'))<div class="invalid-feedback" id="{{ $field('password_min_length') }}Error">{{ $msg('password_min_length') }}</div>@endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="{{ $field('password_expiry_days') }}">{{ __('admin_system.settings.fields.password_expiry_days') }}</label>
                    <input class="form-control @if ($err('password_expiry_days')) is-invalid @endif" type="number" inputmode="numeric" min="0" max="365" id="{{ $field('password_expiry_days') }}" wire:model="form.password_expiry_days" @disabled(! $canManage) aria-describedby="{{ $field('password_expiry_days') }}Hint">
                    <div class="form-text" id="{{ $field('password_expiry_days') }}Hint">{{ __('admin_system.settings.security.expiry_hint') }}</div>
                    @if ($err('password_expiry_days'))<div class="invalid-feedback d-block">{{ $msg('password_expiry_days') }}</div>@endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="{{ $field('session_minutes') }}">{{ __('admin_system.settings.fields.session_minutes') }}</label>
                    <input class="form-control @if ($err('session_minutes')) is-invalid @endif" type="number" inputmode="numeric" min="5" max="1440" id="{{ $field('session_minutes') }}" wire:model="form.session_minutes" @disabled(! $canManage) aria-describedby="{{ $field('session_minutes') }}Hint">
                    <div class="form-text" id="{{ $field('session_minutes') }}Hint">{{ __('admin_system.settings.security.session_hint') }}</div>
                    @if ($err('session_minutes'))<div class="invalid-feedback d-block">{{ $msg('session_minutes') }}</div>@endif
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="{{ $field('password_require_symbol') }}" wire:model="form.password_require_symbol" @disabled(! $canManage)>
                        <label class="form-check-label" for="{{ $field('password_require_symbol') }}">{{ __('admin_system.settings.fields.password_require_symbol') }}</label>
                    </div>
                </div>
                <div class="col-12"><hr class="my-0"></div>
                <div class="col-12">
                    <h3 class="h6">{{ __('admin_system.settings.security.otp_title') }}</h3>
                    <p class="small text-secondary mb-0">{{ __('admin_system.settings.security.otp_text') }}</p>
                </div>
            </div>
            @if ($canManage)<div class="card-footer text-end"><button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="save">{{ __('admin_system.common.save') }}</button></div>@endif
        </form>
    @endif

    {{-- Conservation --}}
    @if ($tab === 'retention')
        <form class="card xm-admin-card mb-4" wire:submit="save('retention')" novalidate aria-labelledby="xmSetRetentionTitle">
            <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSetRetentionTitle">{{ __('admin_system.settings.retention.title') }}</h2></div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="{{ $field('audit_log_days') }}">{{ __('admin_system.settings.fields.audit_log_days') }}</label>
                    <div class="input-group">
                        <input class="form-control @if ($err('audit_log_days')) is-invalid @endif" type="number" inputmode="numeric" min="7" max="3650" id="{{ $field('audit_log_days') }}" wire:model="form.audit_log_days" @disabled(! $canManage) @if ($err('audit_log_days')) aria-invalid="true" aria-describedby="{{ $field('audit_log_days') }}Error" @endif>
                        <span class="input-group-text">{{ __('admin_system.settings.retention.days') }}</span>
                        @if ($err('audit_log_days'))<div class="invalid-feedback" id="{{ $field('audit_log_days') }}Error">{{ $msg('audit_log_days') }}</div>@endif
                    </div>
                    <div class="form-text">{{ __('admin_system.settings.retention.audit_hint') }}</div>
                </div>
                <div class="col-md-7">
                    <h3 class="h6">{{ __('admin_system.settings.retention.others') }}</h3>
                    <ul class="small mb-0">
                        <li>{{ __('admin_system.settings.retention.mail', ['days' => $retention['mail_log_days'] ?? 180]) }}</li>
                        <li>{{ __('admin_system.settings.retention.sync', ['days' => $retention['sync_receipt_days'] ?? 90]) }}</li>
                        <li>{{ __('admin_system.settings.retention.otp', ['hours' => $retention['otp_hours'] ?? 24]) }}</li>
                        <li>{{ __('admin_system.settings.retention.cases', ['years' => $retention['sensitive_years_after_case'] ?? 5]) }}</li>
                    </ul>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-between gap-2 align-items-center">
                @can('audit_logs.view')<a class="small" href="{{ route('admin.journal') }}">{{ __('admin_system.settings.retention.journal_link') }}</a>@endcan
                @if ($canManage)<button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="save">{{ __('admin_system.common.save') }}</button>@endif
            </div>
        </form>
    @endif

    {{-- Messagerie SMTP : superadmin --}}
    @if ($tab === 'smtp')
        <form class="card xm-admin-card mb-4" wire:submit="save('smtp')" novalidate aria-labelledby="xmSetSmtpTitle">
            <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSetSmtpTitle">{{ __('admin_system.settings.smtp.title') }}</h2></div>
            <div class="card-body row g-3">
                <div class="col-12"><p class="small text-secondary mb-0">{{ __('admin_system.settings.smtp.intro') }}</p></div>
                @foreach (['smtp_host' => 'text', 'smtp_port' => 'number', 'smtp_username' => 'text', 'smtp_from_email' => 'email', 'smtp_from_name' => 'text', 'smtp_reply_to' => 'email'] as $key => $type)
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $field($key) }}">{{ __('admin_system.settings.fields.'.$key) }}</label>
                        <input class="form-control @if ($err($key)) is-invalid @endif" type="{{ $type }}" id="{{ $field($key) }}" wire:model="form.{{ $key }}" autocomplete="off" @disabled(! $canManage) @if ($err($key)) aria-invalid="true" aria-describedby="{{ $field($key) }}Error" @endif>
                        @if ($err($key))<div class="invalid-feedback" id="{{ $field($key) }}Error">{{ $msg($key) }}</div>@endif
                    </div>
                @endforeach
                <div class="col-md-6">
                    <label class="form-label" for="{{ $field('smtp_encryption') }}">{{ __('admin_system.settings.fields.smtp_encryption') }}</label>
                    <select class="form-select" id="{{ $field('smtp_encryption') }}" wire:model="form.smtp_encryption" @disabled(! $canManage)>
                        @foreach (['tls', 'ssl', 'none'] as $mode)<option value="{{ $mode }}">{{ __('admin_system.settings.smtp.encryptions.'.$mode) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="{{ $field('smtp_password') }}">{{ __('admin_system.settings.fields.smtp_password') }}</label>
                    <input class="form-control @error('smtpPassword') is-invalid @enderror" type="password" id="{{ $field('smtp_password') }}" wire:model="smtpPassword" autocomplete="new-password" @disabled(! $canManage) aria-describedby="{{ $field('smtp_password') }}Hint">
                    <div class="form-text" id="{{ $field('smtp_password') }}Hint">{{ $smtpSet ? __('admin_system.settings.smtp.password_set') : __('admin_system.settings.smtp.password_unset') }}</div>
                    @error('smtpPassword')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
            @if ($canManage)<div class="card-footer text-end"><button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="save">{{ __('admin_system.common.save') }}</button></div>@endif
        </form>
    @endif

    {{-- Informations système, identité visuelle, garde (lecture seule) --}}
    @if ($tab === 'system')
        <div class="row g-4">
            <div class="col-xl-6">
                <section class="card xm-admin-card h-100" aria-labelledby="xmSetSystemTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSetSystemTitle">{{ __('admin_system.settings.system.title') }}</h2></div>
                    <dl class="list-group list-group-flush small mb-0">
                        @foreach ($this->system as $key => $value)
                            <div class="list-group-item d-flex justify-content-between gap-3"><dt class="fw-normal">{{ __('admin_system.settings.system.items.'.$key) }}</dt><dd class="fw-bold mb-0 text-end text-break">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            </div>
            <div class="col-xl-6">
                <section class="card xm-admin-card mb-4" aria-labelledby="xmSetIdentityTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSetIdentityTitle">{{ __('admin_system.settings.identity.title') }}</h2></div>
                    <div class="card-body">
                        <p class="small text-secondary">{{ __('admin_system.settings.identity.text') }}</p>
                        <div class="p-4 rounded border mb-3 text-center bg-body-tertiary"><x-logo variant="couleur" :width="180" :link="false" /><div class="small text-secondary mt-2">{{ __('admin_system.settings.identity.color') }}</div></div>
                        <div class="p-4 rounded mb-3 text-center xm-settings__dark"><x-logo variant="mono-blanc" :width="180" :link="false" /><div class="small mt-2 text-white-50">{{ __('admin_system.settings.identity.white') }}</div></div>
                        <ul class="list-unstyled d-flex flex-wrap gap-3 mb-0">
                            @foreach ($tokens as $token => $name)
                                <li class="text-center"><span class="xm-settings__swatch d-block" style="background: var({{ $token }})" role="img" aria-label="{{ __('admin_system.settings.identity.colors.'.$name) }}"></span><span class="small text-secondary">{{ __('admin_system.settings.identity.colors.'.$name) }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </section>
                <section class="card xm-admin-card" aria-labelledby="xmSetOnCallTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSetOnCallTitle">{{ __('admin_system.settings.on_call.title') }}</h2></div>
                    <div class="card-body small">
                        @if ($this->onCall === [])
                            <p class="mb-0 text-secondary">{{ __('admin_system.settings.on_call.none') }}</p>
                        @else
                            <p class="mb-1">{{ __('admin_system.settings.on_call.now') }}</p>
                            <ul class="mb-0">@foreach ($this->onCall as $pseudo)<li>{{ $pseudo }}</li>@endforeach</ul>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    @endif
</div>
