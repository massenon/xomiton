{{--
    Gestion des forums (P18-10) : composant App\Livewire\Admin\Network\Forums, onglet « Gestion des forums » de /admin/reseau.
    Liste ordonnée (ordre de la page Forums des membres et de l'application), boutons « Monter » / « Descendre » (annonce
    aria-live, focus conservé), modale de création et de modification (nom, description, icône), archiver / réactiver.
--}}
@php
    $forums = $this->forums;
    $total = $forums->count();
@endphp
<div x-data x-on:xm-forum-focus.window="$nextTick(() => setTimeout(() => document.getElementById($event.detail.id)?.focus(), 30))">
    <div class="card-body border-bottom d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <p class="small text-secondary mb-0 xm-prose">{{ __('admin_network.manage.intro') }}</p>
        <button type="button" class="btn btn-primary btn-sm" wire:click="create"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_network.manage.create') }}</button>
    </div>
    <div class="visually-hidden" role="status" aria-live="polite">{{ $announce }}</div>

    @if ($total === 0)
        <x-admin.empty icon="chat-square-text" :title="__('admin_network.manage.empty')" />
    @else
        <x-admin.table id="xmForumsTable" :caption="__('admin_network.manage.caption')" :columns="[
            ['key' => 'order', 'label' => __('admin_network.manage.columns.order'), 'width' => '7rem'],
            ['key' => 'forum', 'label' => __('admin_network.manage.columns.forum')],
            ['key' => 'topics', 'label' => __('admin_network.manage.columns.topics'), 'class' => 'text-end', 'width' => '6rem'],
            ['key' => 'state', 'label' => __('admin_network.manage.columns.state'), 'width' => '8rem'],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
        ]">
                    @foreach ($forums as $index => $forum)
                        @php($archived = $forum->isArchived())
                        <tr wire:key="forum-{{ $forum->id }}">
                            <td data-label="{{ __('admin_network.manage.columns.order') }}" class="small text-secondary">{{ __('admin_network.manage.position', ['position' => $index + 1, 'total' => $total]) }}</td>
                            <td data-label="{{ __('admin_network.manage.columns.forum') }}">
                                <div class="fw-semibold text-break"><i class="bi {{ $forum->icon }} text-primary me-1" aria-hidden="true"></i>{{ $forum->name }}</div>
                                <div class="small text-secondary text-break">{{ $forum->description }}</div>
                            </td>
                            <td data-label="{{ __('admin_network.manage.columns.topics') }}" class="text-end">{{ (int) $forum->topics_count }}</td>
                            <td data-label="{{ __('admin_network.manage.columns.state') }}">
                                @if (! $forum->is_active)
                                    <x-badge color="secondary" soft icon="eye-slash">{{ __('admin_network.manage.states.closed') }}</x-badge>
                                @elseif ($archived)
                                    <x-badge color="warning" soft icon="archive">{{ __('admin_network.manage.states.archived') }}</x-badge>
                                @else
                                    <x-badge color="success" soft icon="check2-circle">{{ __('admin_network.manage.states.open') }}</x-badge>
                                @endif
                            </td>
                            <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                                <div class="d-flex flex-wrap gap-1 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="xmForumUp{{ $forum->id }}" wire:click="move({{ $forum->id }}, -1)" @disabled($index === 0)><i class="bi bi-arrow-up" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_network.manage.actions.up') }} {{ $forum->name }}</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="xmForumDown{{ $forum->id }}" wire:click="move({{ $forum->id }}, 1)" @disabled($index === $total - 1)><i class="bi bi-arrow-down" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_network.manage.actions.down') }} {{ $forum->name }}</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="edit({{ $forum->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_network.manage.actions.edit') }}<span class="visually-hidden"> {{ $forum->name }}</span></button>
                                    @if ($archived)
                                        <button type="button" class="btn btn-sm btn-outline-success" wire:click="archive({{ $forum->id }}, false)">{{ __('admin_network.manage.actions.restore') }}<span class="visually-hidden"> {{ $forum->name }}</span></button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="archive({{ $forum->id }}, true)" wire:confirm="{{ __('admin_network.manage.confirm_archive', ['name' => $forum->name]) }}"><i class="bi bi-archive me-1" aria-hidden="true"></i>{{ __('admin_network.manage.actions.archive') }}<span class="visually-hidden"> {{ $forum->name }}</span></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
        </x-admin.table>
    @endif

    <x-admin.modal id="xmForumForm" size="lg" :title="$forumId === null ? __('admin_network.manage.create_title') : __('admin_network.manage.edit_title', ['name' => $form['name']])" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()">
        <form id="xmForumFormEl" wire:submit="save" novalidate>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmForumName">{{ __('admin_network.manage.fields.name') }}</label>
                <input class="form-control @error('form.name') is-invalid @enderror" type="text" id="xmForumName" data-xm-autofocus wire:model="form.name" maxlength="{{ \App\Application\Admin\Network\ManageForums::NAME_MAX }}" required autocomplete="off"
                       aria-describedby="xmForumNameHelp @error('form.name') xmForumNameError @enderror" @error('form.name') aria-invalid="true" @enderror>
                <div class="form-text" id="xmForumNameHelp">{{ __('admin_network.manage.help.name', ['min' => \App\Application\Admin\Network\ManageForums::NAME_MIN, 'max' => \App\Application\Admin\Network\ManageForums::NAME_MAX]) }}</div>
                @error('form.name')<div class="invalid-feedback d-block" id="xmForumNameError">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmForumDescription">{{ __('admin_network.manage.fields.description') }}</label>
                <textarea class="form-control @error('form.description') is-invalid @enderror" id="xmForumDescription" rows="2" wire:model="form.description" maxlength="{{ \App\Application\Admin\Network\ManageForums::DESCRIPTION_MAX }}" required
                          aria-describedby="xmForumDescriptionHelp @error('form.description') xmForumDescriptionError @enderror" @error('form.description') aria-invalid="true" @enderror></textarea>
                <div class="form-text" id="xmForumDescriptionHelp">{{ __('admin_network.manage.help.description', ['max' => \App\Application\Admin\Network\ManageForums::DESCRIPTION_MAX]) }}</div>
                @error('form.description')<div class="invalid-feedback d-block" id="xmForumDescriptionError">{{ $message }}</div>@enderror
            </div>
            <fieldset class="mb-0">
                <legend class="form-label fw-semibold fs-6">{{ __('admin_network.manage.fields.icon') }}</legend>
                <p class="form-text mt-0" id="xmForumIconHelp">{{ __('admin_network.manage.help.icon') }}</p>
                <div class="d-flex flex-wrap gap-2" aria-describedby="xmForumIconHelp">
                    @foreach (\App\Application\Admin\Network\ManageForums::ICONS as $icon)
                        <div>
                            <input class="btn-check" type="radio" name="xmForumIcon" id="xmForumIcon-{{ $icon }}" value="{{ $icon }}" wire:model="form.icon">
                            <label class="btn btn-outline-secondary btn-sm" for="xmForumIcon-{{ $icon }}"><i class="bi bi-{{ $icon }} fs-5" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_network.manage.icons.'.$icon) }}</span></label>
                        </div>
                    @endforeach
                </div>
                @error('form.icon')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </fieldset>
            @if ($forumId !== null)
                <p class="small text-secondary mt-3 mb-0">{{ __('admin_network.manage.key', ['key' => $this->forums->firstWhere('id', $forumId)?->key ?? '']) }}</p>
            @endif
        </form>
        <x-slot:footer>
            <button type="submit" form="xmForumFormEl" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $forumId === null ? __('admin_network.manage.create') : __('admin_network.manage.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
