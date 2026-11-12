{{--
    Annonce globale (P18-13, Q97) : composant App\Livewire\Admin\Announcements\Index, gabarit admin.
    Ordre : introduction · bouton « Nouvelle annonce » · tableau (titre, public, période, état, actions) · modale.
    La bannière s'affiche en haut de l'espace membre web et de l'application, jamais sur le site public ni pour les
    visiteur·ses ; chaque personne peut la fermer (fermeture mémorisée par annonce).
--}}
<div class="xm-announcements-admin">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_announcements.intro') }}</p>

    <section class="card xm-admin-card" aria-labelledby="xmAnnTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmAnnTitle">{{ __('admin_announcements.list_title') }}</h2>
            <button type="button" class="btn btn-primary btn-sm" wire:click="create"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_announcements.create') }}</button>
        </div>
        <x-admin.table id="xmAnnTable" :caption="__('admin_announcements.caption')" :empty="$rows->isEmpty()" :columns="[
            ['key' => 'title', 'label' => __('admin_announcements.columns.title')],
            ['key' => 'audience', 'label' => __('admin_announcements.columns.audience'), 'width' => '10rem'],
            ['key' => 'period', 'label' => __('admin_announcements.columns.period'), 'width' => '14rem'],
            ['key' => 'state', 'label' => __('admin_announcements.columns.state'), 'width' => '8rem'],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($rows as $announcement)
                @php($state = $announcement->state())
                @php($attributes = $announcement->getAttributes())
                <tr wire:key="announcement-{{ $announcement->id }}">
                    <td data-label="{{ __('admin_announcements.columns.title') }}">
                        <div class="fw-semibold text-break">{{ $announcement->title }}</div>
                        <div class="small text-secondary text-break">{{ \Illuminate\Support\Str::limit($announcement->message, 120) }}</div>
                        <div class="mt-1"><x-badge :color="$announcement->level()->badge()" soft>{{ $announcement->level()->label() }}</x-badge></div>
                    </td>
                    <td data-label="{{ __('admin_announcements.columns.audience') }}" class="small">{{ $announcement->audience()->label() }}</td>
                    <td data-label="{{ __('admin_announcements.columns.period') }}" class="small">
                        {{ __('admin_announcements.from', ['date' => $format->tableDateTime($announcement->asDateTimePublic($attributes['starts_at']))]) }}<br>
                        {{ ($attributes['ends_at'] ?? null) !== null ? __('admin_announcements.until', ['date' => $format->tableDateTime($announcement->asDateTimePublic($attributes['ends_at']))]) : __('admin_announcements.no_end') }}
                    </td>
                    <td data-label="{{ __('admin_announcements.columns.state') }}"><x-badge :color="['programmee' => 'info', 'en_cours' => 'success', 'terminee' => 'neutral'][$state]" soft>{{ __('admin_announcements.states.'.$state) }}</x-badge></td>
                    <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $announcement->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_announcements.actions.edit') }}<span class="visually-hidden"> {{ $announcement->title }}</span></button>
                            @if ($state === 'en_cours')
                                <button type="button" class="btn btn-sm btn-outline-warning" wire:click="end({{ $announcement->id }})" wire:confirm="{{ __('admin_announcements.confirm_end', ['title' => $announcement->title]) }}">{{ __('admin_announcements.actions.end') }}<span class="visually-hidden"> {{ $announcement->title }}</span></button>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $announcement->id }})" wire:confirm="{{ __('admin_announcements.confirm_delete', ['title' => $announcement->title]) }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_announcements.actions.delete') }} {{ $announcement->title }}</span></button>
                        </div>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState><x-admin.empty icon="megaphone" :title="__('admin_announcements.empty')" /></x-slot:emptyState>
            <x-slot:footer><x-admin.pagination id="xmAnnPager" :paginator="$rows" /></x-slot:footer>
        </x-admin.table>
    </section>

    <x-admin.modal id="xmAnnouncement" size="lg" :title="$announcementId === null ? __('admin_announcements.create_title') : __('admin_announcements.edit_title')" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()">
        <form id="xmAnnouncementForm" wire:submit="save" novalidate>
            @foreach ([['title', 'text', \App\Application\Platform\Announcements\AnnouncementRules::TITLE_MAX], ['link_path', 'text', \App\Application\Platform\Announcements\AnnouncementRules::LINK_MAX]] as [$field, $type, $max])
                @if ($field === 'link_path')
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="xmAnnMessage">{{ __('admin_announcements.fields.message') }}</label>
                        <textarea class="form-control @error('form.message') is-invalid @enderror" id="xmAnnMessage" rows="3" wire:model="form.message" maxlength="{{ \App\Application\Platform\Announcements\AnnouncementRules::MESSAGE_MAX }}" required
                                  aria-describedby="xmAnnMessageHelp @error('form.message') xmAnnMessageError @enderror" @error('form.message') aria-invalid="true" @enderror></textarea>
                        <div class="form-text" id="xmAnnMessageHelp">{{ __('admin_announcements.help.message', ['max' => \App\Application\Platform\Announcements\AnnouncementRules::MESSAGE_MAX]) }}</div>
                        @error('form.message')<div class="invalid-feedback d-block" id="xmAnnMessageError">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="xmAnn-{{ $field }}">{{ __('admin_announcements.fields.'.$field) }}</label>
                    <input class="form-control @error('form.'.$field) is-invalid @enderror" type="{{ $type }}" id="xmAnn-{{ $field }}" wire:model="form.{{ $field }}" maxlength="{{ $max }}" autocomplete="off" @if ($field === 'title') data-xm-autofocus required @endif
                           aria-describedby="xmAnn-{{ $field }}Help @error('form.'.$field) xmAnn-{{ $field }}Error @enderror" @error('form.'.$field) aria-invalid="true" @enderror>
                    <div class="form-text" id="xmAnn-{{ $field }}Help">{{ __('admin_announcements.help.'.$field, ['max' => $max]) }}</div>
                    @error('form.'.$field)<div class="invalid-feedback d-block" id="xmAnn-{{ $field }}Error">{{ $message }}</div>@enderror
                </div>
            @endforeach
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="xmAnnLevel">{{ __('admin_announcements.fields.level') }}</label>
                    <select class="form-select" id="xmAnnLevel" wire:model="form.level">
                        @foreach (\App\Enums\AnnouncementLevel::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="xmAnnAudience">{{ __('admin_announcements.fields.audience') }}</label>
                    <select class="form-select" id="xmAnnAudience" wire:model="form.audience" aria-describedby="xmAnnAudienceHelp">
                        @foreach (\App\Enums\AnnouncementAudience::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                    </select>
                    <div class="form-text" id="xmAnnAudienceHelp">{{ __('admin_announcements.help.audience') }}</div>
                </div>
            </div>
            <div class="row g-3">
                @foreach (['starts_at', 'ends_at'] as $field)
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold" for="xmAnn-{{ $field }}">{{ __('admin_announcements.fields.'.$field) }}</label>
                        <input class="form-control @error('form.'.$field) is-invalid @enderror" type="datetime-local" id="xmAnn-{{ $field }}" wire:model="form.{{ $field }}" @if ($field === 'starts_at') required @endif
                               aria-describedby="xmAnn-{{ $field }}Help @error('form.'.$field) xmAnn-{{ $field }}Error @enderror" @error('form.'.$field) aria-invalid="true" @enderror>
                        <div class="form-text" id="xmAnn-{{ $field }}Help">{{ __('admin_announcements.help.'.$field) }}</div>
                        @error('form.'.$field)<div class="invalid-feedback d-block" id="xmAnn-{{ $field }}Error">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmAnnouncementForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_announcements.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
