{{--
    Templates e-mails (P14-01) : composant App\Livewire\Admin\Emails\Index, gabarit admin.
    Ordre : introduction · KPI · liste (filtres, tableau, actions) · état de l'envoi (SMTP, boîte d'envoi) et derniers envois · modales.
    Éditeur : champ HTML (le HTML est nettoyé à l'enregistrement, HTMLPurifier) ; variables cliquables insérées à la position du
    curseur du dernier champ actif ; aperçu bureau / mobile avec données fictives ; test déposé dans la boîte d'envoi.
--}}
<div class="xm-emails-admin">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_newsletter.emails.intro') }}</p>

    <x-admin.kpi id="xmEmailKpis" :items="[
        ['label' => __('admin.emails.kpi.total'), 'value' => $format->number($this->stats['total']), 'icon' => 'envelope-at'],
        ['label' => __('admin.emails.kpi.active'), 'value' => $format->number($this->stats['active']), 'icon' => 'toggle-on', 'variant' => 'success'],
        ['label' => __('admin.emails.kpi.system'), 'value' => $format->number($this->stats['system']), 'icon' => 'shield-lock', 'variant' => 'violet'],
        ['label' => __('admin.emails.kpi.sent_30d'), 'value' => $format->number($this->stats['sent_30d']), 'icon' => 'send-check', 'variant' => 'warning'],
    ]" />

    <section class="card xm-admin-card mb-4" aria-labelledby="xmEmailListTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmEmailListTitle">{{ __('admin.emails.title') }}</h2>
            @can('create', \App\Models\EmailTemplate::class)
                <button type="button" class="btn btn-primary btn-sm" wire:click="create"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_newsletter.emails.new') }}</button>
            @endcan
        </div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmEmailFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_newsletter.emails.search_placeholder')">
                <div class="col-6 col-lg-3">
                    <label class="visually-hidden" for="xmEmailCategory">{{ __('admin.emails.columns.category') }}</label>
                    <select class="form-select form-select-sm" id="xmEmailCategory" wire:model.live="category">
                        <option value="">{{ __('admin_newsletter.emails.all_categories') }}</option>
                        @foreach (\App\Enums\TemplateCategory::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="xmEmailState">{{ __('admin.emails.columns.status') }}</label>
                    <select class="form-select form-select-sm" id="xmEmailState" wire:model.live="state">
                        <option value="">{{ __('admin_newsletter.emails.all_states') }}</option>
                        <option value="actif">{{ __('admin_newsletter.emails.state_active') }}</option>
                        <option value="inactif">{{ __('admin.emails.badges.inactive') }}</option>
                    </select>
                </div>
            </x-admin.filters>
        </div>

        <x-admin.table id="xmEmailTable" :caption="__('admin_newsletter.emails.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
            ['key' => 'nom', 'label' => __('admin.emails.columns.name'), 'sortable' => true],
            ['key' => 'slug', 'label' => __('admin.emails.columns.slug'), 'sortable' => true],
            ['key' => 'categorie', 'label' => __('admin.emails.columns.category'), 'sortable' => true],
            ['key' => 'modifie', 'label' => __('admin.emails.columns.updated'), 'sortable' => true],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin_newsletter.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($rows as $template)
                <tr wire:key="tpl-{{ $template->id }}">
                    <td data-label="{{ __('admin.emails.columns.name') }}">
                        <div class="min-w-0">
                            <span class="d-block fw-semibold text-break">{{ $template->name }}</span>
                            <span class="d-flex flex-wrap gap-1 mt-1">
                                @if ($template->is_system)<x-badge color="violet" soft icon="shield-lock">{{ __('admin.emails.badges.system') }}</x-badge>@endif
                                @unless ($template->is_active)<x-badge color="neutral" icon="slash-circle">{{ __('admin.emails.badges.inactive') }}</x-badge>@endunless
                            </span>
                        </div>
                    </td>
                    <td data-label="{{ __('admin.emails.columns.slug') }}"><code class="small text-break">{{ $template->slug }}</code></td>
                    <td data-label="{{ __('admin.emails.columns.category') }}"><x-badge :color="$template->category->badge()" soft>{{ $template->category->label() }}</x-badge></td>
                    <td data-label="{{ __('admin.emails.columns.updated') }}"><time class="small" datetime="{{ $template->updated_at?->toIso8601String() }}">{{ $format->tableDateTime($template->updated_at) }}</time></td>
                    <td class="xm-table__actions text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $template->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_newsletter.edit') }}<span class="visually-hidden"> {{ $template->name }}</span></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="duplicate({{ $template->id }})"><i class="bi bi-copy me-1" aria-hidden="true"></i>{{ __('admin_newsletter.duplicate') }}<span class="visually-hidden"> {{ $template->name }}</span></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggle({{ $template->id }})" aria-pressed="{{ $template->is_active ? 'true' : 'false' }}"><i class="bi bi-toggle-{{ $template->is_active ? 'on' : 'off' }} me-1" aria-hidden="true"></i>{{ $template->is_active ? __('admin_newsletter.emails.deactivate') : __('admin_newsletter.emails.activate') }}<span class="visually-hidden"> {{ $template->name }}</span></button>
                        @can('delete', $template)
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="askDelete({{ $template->id }})"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_newsletter.delete') }}<span class="visually-hidden"> {{ $template->name }}</span></button>
                        @endcan
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-admin.empty icon="envelope-at" :filtered="$this->activeFilterCount() > 0" :title="__('admin.emails.empty')" />
            </x-slot:emptyState>
            <x-slot:footer>
                <x-admin.pagination id="xmEmailPager" :paginator="$rows" />
            </x-slot:footer>
        </x-admin.table>
    </section>

    <div class="row g-4">
        <div class="col-xl-4">
            <section class="card xm-admin-card h-100" aria-labelledby="xmEmailSmtpTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmEmailSmtpTitle"><i class="bi bi-hdd-network me-2" aria-hidden="true"></i>{{ __('admin.emails.smtp.title') }}</h2></div>
                <div class="card-body small">
                    <p class="mb-2"><x-badge :color="$smtp['configured'] ? 'success' : 'warning'" soft>{{ $smtp['configured'] ? __('admin.emails.smtp.configured') : __('admin.emails.smtp.not_configured') }}</x-badge></p>
                    <dl class="row mb-2">
                        <dt class="col-5">{{ __('admin.emails.smtp.host') }}</dt><dd class="col-7 text-break">{{ $smtp['configured'] ? $smtp['host'] : __('admin_newsletter.emails.env_mailer', ['mailer' => $smtp['mailer']]) }}</dd>
                        <dt class="col-5">{{ __('admin.emails.smtp.from_email') }}</dt><dd class="col-7 text-break">{{ $smtp['from'] }}</dd>
                    </dl>
                    <p class="mb-2">
                        {{ trans_choice('admin.emails.smtp.outbox', $this->outbox['pending'], ['count' => $format->number($this->outbox['pending'])]) }}
                        @if ($this->outbox['failed'] > 0)<span class="text-danger">{{ trans_choice('admin_newsletter.emails.outbox_failed', $this->outbox['failed'], ['count' => $this->outbox['failed']]) }}</span>@endif
                    </p>
                    <p class="text-secondary mb-2">{{ __('admin_newsletter.emails.smtp_help') }}</p>
                    @if ($canSettings && Route::has('admin.parametres'))
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.parametres') }}"><i class="bi bi-gear me-1" aria-hidden="true"></i>{{ __('admin_newsletter.emails.open_settings') }}</a>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-xl-8">
            <section class="card xm-admin-card h-100" aria-labelledby="xmEmailRecentTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmEmailRecentTitle">{{ __('admin.emails.recent.title') }}</h2></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($this->recent as $log)
                        <li class="list-group-item d-flex flex-wrap gap-2 align-items-center" wire:key="log-{{ $log->id }}">
                            <time datetime="{{ $log->created_at?->toIso8601String() }}" class="text-secondary">{{ $format->tableDateTime($log->created_at) }}</time>
                            <code>{{ $log->template_slug ?? '—' }}</code>
                            <span class="text-secondary">{{ \App\Livewire\Admin\Emails\Index::mask((string) $log->to) }}</span>
                            <x-badge :color="$log->ok ? 'success' : 'danger'" soft class="ms-auto">{{ $log->ok ? $log->transport->value : __('admin_newsletter.emails.failed') }}</x-badge>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-secondary py-4">{{ __('admin.emails.recent.empty') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>

    {{-- Modale d'édition : volets « Édition » et « Aperçu » dans la même modale (pas de modale dans la modale). --}}
    <x-admin.modal id="xmEmailEdit" size="xl" dirty-guard :title="$templateId === null ? __('admin_newsletter.emails.new') : __('admin_newsletter.emails.edit')">
        <div x-data="{ target: null, insert(name) { const el = this.target || $refs.body; const text = '{' + '{' + name + '}' + '}'; const at = el.selectionStart ?? el.value.length; el.setRangeText(text, at, el.selectionEnd ?? at, 'end'); el.dispatchEvent(new Event('input', { bubbles: true })); el.focus(); } }"
             x-on:focusin="if ($event.target.matches('[data-xm-var-target]')) target = $event.target">
            <div class="btn-group mb-3" role="group" aria-label="{{ __('admin_newsletter.emails.panes') }}">
                <button type="button" @class(['btn btn-sm', 'btn-primary' => $pane === 'edit', 'btn-outline-primary' => $pane !== 'edit']) wire:click="setPane('edit')" aria-pressed="{{ $pane === 'edit' ? 'true' : 'false' }}">{{ __('admin_newsletter.emails.pane_edit') }}</button>
                <button type="button" @class(['btn btn-sm', 'btn-primary' => $pane === 'preview', 'btn-outline-primary' => $pane !== 'preview']) wire:click="setPane('preview')" aria-pressed="{{ $pane === 'preview' ? 'true' : 'false' }}">{{ __('admin.emails.preview.title') }}</button>
            </div>

            <form id="xmEmailForm" wire:submit="save" novalidate @class(['d-none' => $pane !== 'edit'])>
                <div class="row g-3">
                    <div class="col-md-6"><x-field name="form.name" :label="__('admin.emails.fields.name')" wire:model.blur="form.name" maxlength="80" :hint="__('admin.emails.fields.name_help', ['min' => 3, 'max' => 80])" /></div>
                    <div class="col-md-3">
                        @if ($editing?->is_system)
                            <x-field name="form.slug" :label="__('admin.emails.fields.slug')" wire:model="form.slug" readonly :hint="__('admin.emails.fields.slug_locked')" />
                        @else
                            <x-field name="form.slug" :label="__('admin.emails.fields.slug')" wire:model.blur="form.slug" maxlength="60" optional :hint="__('admin.emails.fields.slug_help')" />
                        @endif
                    </div>
                    <div class="col-md-3"><x-field name="form.category" type="select" :label="__('admin.emails.fields.category')" wire:model="form.category" :options="collect(\App\Enums\TemplateCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()" /></div>
                    <div class="col-md-6"><x-field name="form.subject" :label="__('admin.emails.fields.subject')" wire:model.blur="form.subject" maxlength="150" data-xm-var-target :hint="__('admin.emails.fields.subject_help', ['min' => 3, 'max' => 150])" /></div>
                    <div class="col-md-6"><x-field name="form.preheader" :label="__('admin.emails.fields.preheader')" wire:model.blur="form.preheader" maxlength="150" optional data-xm-var-target :hint="__('admin.emails.fields.preheader_help')" /></div>
                    <div class="col-12">
                        <x-field name="form.body" type="textarea" :label="__('admin.emails.fields.body')" wire:model.blur="form.body" rows="12" x-ref="body" data-xm-var-target class="font-monospace" :hint="__('admin_newsletter.emails.body_help')" />
                    </div>
                    <div class="col-12">
                        <div class="fw-semibold mb-1" id="xmEmailVarsLabel">{{ __('admin.emails.fields.variables') }}</div>
                        <p class="small text-secondary mb-2">{{ __('admin.emails.fields.variables_help') }}</p>
                        <div class="d-flex flex-wrap gap-1" role="group" aria-labelledby="xmEmailVarsLabel">
                            @foreach ($this->variables as $name => $sample)
                                <button type="button" class="btn btn-sm btn-outline-secondary font-monospace" x-on:click="insert(@js($name))" title="{{ __('admin_newsletter.emails.variable_sample', ['value' => $sample]) }}" wire:key="var-{{ $name }}">{{ '{'.'{'.$name.'}'.'}' }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="xmEmailActive" wire:model="form.is_active"><label class="form-check-label" for="xmEmailActive">{{ __('admin.emails.fields.active') }}</label></div>
                    </div>
                </div>
            </form>

            @if ($pane === 'preview')
                <section aria-labelledby="xmEmailPreviewTitle">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h3 class="h6 mb-0" id="xmEmailPreviewTitle">{{ __('admin.emails.preview.subject', ['subject' => $previewSubject]) }}</h3>
                        <div class="btn-group" role="group" aria-label="{{ __('admin.emails.preview.title') }}">
                            <button type="button" @class(['btn btn-sm', 'btn-primary' => $previewMode === 'desktop', 'btn-outline-primary' => $previewMode !== 'desktop']) wire:click="setPreviewMode('desktop')" aria-pressed="{{ $previewMode === 'desktop' ? 'true' : 'false' }}"><i class="bi bi-display me-1" aria-hidden="true"></i>{{ __('admin.emails.preview.desktop') }}</button>
                            <button type="button" @class(['btn btn-sm', 'btn-primary' => $previewMode === 'mobile', 'btn-outline-primary' => $previewMode !== 'mobile']) wire:click="setPreviewMode('mobile')" aria-pressed="{{ $previewMode === 'mobile' ? 'true' : 'false' }}"><i class="bi bi-phone me-1" aria-hidden="true"></i>{{ __('admin.emails.preview.mobile') }}</button>
                        </div>
                    </div>
                    <p class="small text-secondary">{{ __('admin.emails.preview.help') }}</p>
                    @if ($previewUnknown !== [])
                        <div class="alert alert-warning small" role="status">{{ __('admin_newsletter.emails.unknown_variables', ['list' => implode(', ', $previewUnknown)]) }}</div>
                    @endif
                    <div class="border rounded bg-body-tertiary p-2 text-center">
                        <iframe class="bg-white border-0 rounded" title="{{ __('admin.emails.preview.title') }}" sandbox srcdoc="{{ $previewHtml }}" style="width: {{ $previewMode === 'mobile' ? '375px' : '100%' }}; max-width: 100%; height: 520px"></iframe>
                    </div>
                </section>
            @endif

            <hr>
            <div class="row g-2 align-items-end">
                <div class="col-md-7">
                    <label class="form-label fw-semibold" for="xmEmailTestTo">{{ __('admin.emails.test.address') }}</label>
                    <input class="form-control @error('testTo') is-invalid @enderror" type="email" id="xmEmailTestTo" wire:model="testTo" autocomplete="off" aria-describedby="xmEmailTestHelp">
                    @error('testTo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" id="xmEmailTestHelp">{{ __('admin_newsletter.emails.test.help') }}</div>
                </div>
                <div class="col-md-auto"><button type="button" class="btn btn-outline-primary" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('admin.emails.test.submit') }}</button></div>
            </div>
        </div>
        <x-slot:footer>
            <button type="submit" form="xmEmailForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ __('admin_newsletter.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal id="xmEmailDelete" type="delete" size="md" :title="__('admin.emails.delete_title')" static>
        @if ($deleting)
            <p>{{ __('admin.emails.delete_text', ['name' => $deleting->name]) }}</p>
            @if ($deleting->is_system)
                <div class="alert alert-danger small mb-0">
                    <label class="form-label mb-1" for="xmEmailDeleteSlug">{{ __('admin.emails.delete_system') }} <code>{{ $deleting->slug }}</code></label>
                    <input class="form-control form-control-sm font-monospace @error('deleteSlug') is-invalid @enderror" id="xmEmailDeleteSlug" autocomplete="off" wire:model="deleteSlug">
                    @error('deleteSlug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-danger" wire:click="delete"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('components.modal.delete_action') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
