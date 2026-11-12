{{--
    Onglet « Conversations en BD » de /admin/ccc (P20, PRD P0-9) : composant App\Livewire\Admin\Ccc\Conversations.
    Sections : Conversations (liste : statut, cases, images manquantes, ordre, publier / dépublier, modifier ; éditeur en
    ligne : textes, puis case par case la description de la scène et les bulles, aperçu et contrôles de la grammaire) ·
    Bibliothèque de scènes (description, active / inactive, image présente). Formulaire simple, utilisable au clavier.
--}}
@php
    $editing = $this->editing;
    $opened = $this->openedPanel();
@endphp
<div class="xm-bdc-admin" x-data x-on:xm-bdc-focus.window="$nextTick(() => document.getElementById($event.detail.id)?.focus())">
    <p class="text-secondary xm-prose">{{ __('admin_conversations_bd.intro') }}</p>
    <div class="btn-group mb-3" role="group" aria-label="{{ __('admin_conversations_bd.sections_label') }}">
        @foreach (\App\Livewire\Admin\Ccc\Conversations::SECTIONS as $key)
            <button type="button" @class(['btn btn-sm', 'btn-primary' => $section === $key, 'btn-outline-primary' => $section !== $key]) wire:click="setSection('{{ $key }}')" @if ($section === $key) aria-current="page" @endif>{{ __('admin_conversations_bd.sections.'.$key) }}</button>
        @endforeach
    </div>
    <p class="visually-hidden" aria-live="polite">{{ $announce }}</p>

    @if ($section === 'scenes')
        <section aria-labelledby="xmBdcScenesTitle">
            <h2 class="h6 fw-bold" id="xmBdcScenesTitle">{{ __('admin_conversations_bd.scenes_title') }}</h2>
            <p class="small text-secondary">{{ __('admin_conversations_bd.scenes_help') }}</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <caption class="visually-hidden">{{ __('admin_conversations_bd.scenes_title') }}</caption>
                    <thead><tr>
                        <th scope="col">{{ __('admin_conversations_bd.columns.scene') }}</th>
                        <th scope="col">{{ __('admin_conversations_bd.columns.description') }}</th>
                        <th scope="col">{{ __('admin_conversations_bd.columns.image') }}</th>
                        <th scope="col">{{ __('admin_conversations_bd.columns.status') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('admin_conversations_bd.columns.actions') }}</span></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($this->scenes as $scene)
                            <tr wire:key="scene-{{ $scene->id }}">
                                <td class="text-nowrap"><code>{{ $scene->key }}</code></td>
                                <td class="small">
                                    @if ($sceneId === $scene->id)
                                        <form wire:submit="saveScene" novalidate>
                                            <label class="visually-hidden" for="xmBdcSceneText">{{ __('admin_conversations_bd.fields.scene_description') }}</label>
                                            <textarea class="form-control form-control-sm @error('sceneText') is-invalid @enderror" id="xmBdcSceneText" rows="3" wire:model="sceneText" maxlength="{{ \App\Application\Admin\Content\ManageConversationsBd::TEXT_MAX }}"></textarea>
                                            @error('sceneText')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                            <button type="submit" class="btn btn-sm btn-primary mt-1">{{ __('admin_conversations_bd.save') }}</button>
                                        </form>
                                    @else
                                        {{ $scene->description }}
                                    @endif
                                </td>
                                <td>
                                    @if ($scene->hasImage())
                                        <span class="badge bg-success-subtle text-success-emphasis">{{ __('admin_conversations_bd.image_ok') }}</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis">{{ __('admin_conversations_bd.image_missing') }}</span>
                                    @endif
                                </td>
                                <td>{{ $scene->is_active ? __('admin_conversations_bd.active') : __('admin_conversations_bd.inactive') }}</td>
                                <td class="text-nowrap text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editScene({{ $scene->id }})">{{ __('admin_conversations_bd.edit') }}<span class="visually-hidden"> {{ $scene->key }}</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleScene({{ $scene->id }})">{{ $scene->is_active ? __('admin_conversations_bd.deactivate') : __('admin_conversations_bd.activate') }}<span class="visually-hidden"> {{ $scene->key }}</span></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section aria-labelledby="xmBdcListTitle">
            <h2 class="h6 fw-bold" id="xmBdcListTitle">{{ __('admin_conversations_bd.list_title') }}</h2>
            @if ($this->conversations->isEmpty())
                <x-empty-state :title="__('admin_conversations_bd.empty')" icon="chat-square-quote" compact />
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <caption class="visually-hidden">{{ __('admin_conversations_bd.list_title') }}</caption>
                        <thead><tr>
                            <th scope="col">{{ __('admin_conversations_bd.columns.order') }}</th>
                            <th scope="col">{{ __('admin_conversations_bd.columns.title') }}</th>
                            <th scope="col">{{ __('admin_conversations_bd.columns.status') }}</th>
                            <th scope="col">{{ __('admin_conversations_bd.columns.panels') }}</th>
                            <th scope="col"><span class="visually-hidden">{{ __('admin_conversations_bd.columns.actions') }}</span></th>
                        </tr></thead>
                        <tbody>
                            @foreach ($this->conversations as $conversation)
                                @php($status = $conversation->statusValue())
                                <tr wire:key="bdc-{{ $conversation->id }}">
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-light" wire:click="move({{ $conversation->id }}, -1)" @disabled($loop->first) aria-label="{{ __('admin_conversations_bd.move_up', ['title' => $conversation->title]) }}"><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
                                        <button type="button" class="btn btn-sm btn-light" wire:click="move({{ $conversation->id }}, 1)" @disabled($loop->last) aria-label="{{ __('admin_conversations_bd.move_down', ['title' => $conversation->title]) }}"><i class="bi bi-arrow-down" aria-hidden="true"></i></button>
                                    </td>
                                    <td>
                                        <span class="fw-semibold">{{ $conversation->title }}</span>
                                        <span class="d-block small text-secondary">{{ $conversation->model_key ? __('admin_conversations_bd.model', ['key' => $conversation->model_key]) : __('admin_conversations_bd.member') }} · {{ $conversation->theme }}</span>
                                    </td>
                                    <td><span class="badge bg-{{ $status?->badge() ?? 'secondary' }}">{{ $status?->label() }}</span></td>
                                    <td class="small">
                                        {{ $conversation->panels_count }}
                                        @if (($missing[$conversation->id] ?? 0) > 0)
                                            <span class="d-block text-warning-emphasis">{{ trans_choice('admin_conversations_bd.images_missing', $missing[$conversation->id], ['count' => $missing[$conversation->id]]) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $conversation->id }})">{{ __('admin_conversations_bd.edit') }}<span class="visually-hidden"> {{ $conversation->title }}</span></button>
                                        <button type="button" @class(['btn btn-sm', 'btn-outline-warning' => $conversation->isPublished(), 'btn-success' => ! $conversation->isPublished()]) wire:click="toggle({{ $conversation->id }})">{{ $conversation->isPublished() ? __('admin_conversations_bd.unpublish') : __('admin_conversations_bd.publish') }}<span class="visually-hidden"> {{ $conversation->title }}</span></button>
                                        @if ($conversation->isPublished())
                                            <a class="btn btn-sm btn-link" href="{{ route('conversations-bd.show', ['slug' => $conversation->slug]) }}" target="_blank" rel="noopener">{{ __('admin_conversations_bd.view') }}<span class="visually-hidden"> {{ $conversation->title }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($editing)
            <section class="card mt-4" aria-labelledby="xmBdcEditTitle" wire:key="bdc-editor-{{ $editing->id }}">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 fw-bold mb-0" id="xmBdcEditTitle" tabindex="-1">{{ __('admin_conversations_bd.edit_title', ['title' => $editing->title]) }}</h2>
                    <button type="button" class="btn btn-sm btn-light" wire:click="closeEditor">{{ __('admin_conversations_bd.close') }}</button>
                </div>
                <div class="card-body vstack gap-4">
                    <form wire:submit="saveTexts" novalidate aria-labelledby="xmBdcTextsTitle">
                        <h3 class="h6" id="xmBdcTextsTitle">{{ __('admin_conversations_bd.texts_title') }}</h3>
                        <div class="row g-3">
                            @foreach (['title' => 'text', 'theme' => 'text', 'category' => 'text', 'audience' => 'text', 'summary' => 'textarea', 'keyMessage' => 'textarea', 'contentWarning' => 'textarea', 'questions' => 'textarea'] as $field => $kind)
                                @php($label = __('admin_conversations_bd.fields.'.\Illuminate\Support\Str::snake($field)))
                                <div class="{{ $kind === 'text' ? 'col-md-6' : 'col-12' }}">
                                    <label class="form-label fw-semibold" for="xmBdc-{{ $field }}">{{ $label }}</label>
                                    @if ($kind === 'text')
                                        <input type="text" class="form-control @error($field) is-invalid @enderror" id="xmBdc-{{ $field }}" wire:model="{{ $field }}" autocomplete="off" @error($field) aria-invalid="true" aria-describedby="xmBdc-{{ $field }}-error" @enderror>
                                    @else
                                        <textarea class="form-control @error($field) is-invalid @enderror" id="xmBdc-{{ $field }}" rows="{{ $field === 'questions' ? 4 : 2 }}" wire:model="{{ $field }}" @error($field) aria-invalid="true" aria-describedby="xmBdc-{{ $field }}-error" @enderror></textarea>
                                        @if ($field === 'questions')<div class="form-text">{{ __('admin_conversations_bd.questions_help') }}</div>@endif
                                    @endif
                                    @error($field)<div class="invalid-feedback d-block" id="xmBdc-{{ $field }}-error">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled" wire:target="saveTexts">{{ __('admin_conversations_bd.save_texts') }}</button>
                    </form>

                    <section aria-labelledby="xmBdcPanelsTitle">
                        <h3 class="h6" id="xmBdcPanelsTitle">{{ __('admin_conversations_bd.panels_title') }}</h3>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ($editing->panels as $panel)
                                <button type="button" @class(['btn btn-sm', 'btn-primary' => $panelId === $panel->id, 'btn-outline-primary' => $panelId !== $panel->id]) wire:click="openPanel({{ $panel->id }})" @if ($panelId === $panel->id) aria-current="true" @endif>{{ __('admin_conversations_bd.panel', ['n' => $panel->position]) }}</button>
                            @endforeach
                        </div>

                        @if ($opened)
                            <div class="row g-4">
                                <div class="col-xl-5 order-xl-2">
                                    <p class="small fw-semibold mb-1">{{ __('admin_conversations_bd.preview') }}</p>
                                    <x-bd.panel :bubbles="$preview['bubbles']" :names="$this->characters" :image="$preview['image']" :alt="$sceneDescription" :n="$opened->position" :count="$editing->panels->count()" :title="$editing->title" />
                                    @if ($preview['issues'] !== [])
                                        <ul class="small mt-2 mb-0 ps-3" aria-label="{{ __('admin_conversations_bd.issues_label') }}">
                                            @foreach ($preview['issues'] as $issue)
                                                <li @class(['text-danger' => $issue['level'] === 'error', 'text-warning-emphasis' => $issue['level'] !== 'error'])>{{ $issue['message'] }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="small text-success-emphasis mt-2 mb-0"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ __('admin_conversations_bd.grammar_ok') }}</p>
                                    @endif
                                </div>
                                <form class="col-xl-7 order-xl-1" wire:submit="savePanel" novalidate aria-label="{{ __('admin_conversations_bd.panel', ['n' => $opened->position]) }}">
                                    @error('bubbles')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold" for="xmBdcSceneDescription">{{ __('admin_conversations_bd.fields.scene_description') }}</label>
                                        <textarea class="form-control" id="xmBdcSceneDescription" rows="2" wire:model.blur="sceneDescription"></textarea>
                                        <div class="form-text">{{ __('admin_conversations_bd.scene_help', ['key' => $opened->scene?->key ?? '—']) }}</div>
                                    </div>
                                    <ol class="list-unstyled vstack gap-3 mb-3">
                                        @foreach ($bubbles as $i => $row)
                                            <li class="border rounded-3 p-2" wire:key="bubble-{{ $panelId }}-{{ $i }}">
                                                <fieldset>
                                                    <legend class="fs-6 fw-semibold mb-2">{{ __('admin_conversations_bd.bubble', ['n' => $i + 1]) }}</legend>
                                                    <div class="row g-2">
                                                        <div class="col-sm-6">
                                                            <label class="form-label small" for="xmBdcType{{ $i }}">{{ __('admin_conversations_bd.fields.type') }}</label>
                                                            <select class="form-select form-select-sm" id="xmBdcType{{ $i }}" wire:model.live="bubbles.{{ $i }}.type">
                                                                @foreach ($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <label class="form-label small" for="xmBdcChar{{ $i }}">{{ __('admin_conversations_bd.fields.character') }}</label>
                                                            <select class="form-select form-select-sm" id="xmBdcChar{{ $i }}" wire:model.live="bubbles.{{ $i }}.personnage">
                                                                <option value="">{{ __('admin_conversations_bd.no_character') }}</option>
                                                                @foreach ($this->characters as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label small" for="xmBdcText{{ $i }}">{{ __('admin_conversations_bd.fields.text') }}</label>
                                                            <textarea class="form-control form-control-sm @error('bubbles.'.$i.'.texte') is-invalid @enderror" id="xmBdcText{{ $i }}" rows="2" wire:model.blur="bubbles.{{ $i }}.texte"></textarea>
                                                            @error('bubbles.'.$i.'.texte')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label small" for="xmBdcEmph{{ $i }}">{{ __('admin_conversations_bd.fields.emphasis') }}</label>
                                                            <input type="text" class="form-control form-control-sm" id="xmBdcEmph{{ $i }}" wire:model.blur="bubbles.{{ $i }}.emphase" autocomplete="off">
                                                        </div>
                                                        @foreach (['x' => 'x', 'y' => 'y', 'tx' => 'tail_x', 'ty' => 'tail_y'] as $coord => $labelKey)
                                                            <div class="col-6 col-md-3">
                                                                <label class="form-label small" for="xmBdc{{ $coord }}{{ $i }}">{{ __('admin_conversations_bd.fields.'.$labelKey) }}</label>
                                                                <input type="number" step="0.5" min="-10" max="110" class="form-control form-control-sm @error('bubbles.'.$i.'.'.$coord) is-invalid @enderror" id="xmBdc{{ $coord }}{{ $i }}" wire:model.blur="bubbles.{{ $i }}.{{ $coord }}" inputmode="decimal">
                                                            </div>
                                                        @endforeach
                                                        <div class="col-sm-6">
                                                            <label class="form-label small" for="xmBdcTail{{ $i }}">{{ __('admin_conversations_bd.fields.tail') }}</label>
                                                            <select class="form-select form-select-sm" id="xmBdcTail{{ $i }}" wire:model.live="bubbles.{{ $i }}.vers">
                                                                <option value="">{{ __('admin_conversations_bd.no_tail') }}</option>
                                                                @foreach ($targets as $target)<option value="{{ $target->value }}">{{ __('conversations_bd.tail_targets.'.$target->value) }}</option>@endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <label class="form-label small" for="xmBdcLink{{ $i }}">{{ __('admin_conversations_bd.fields.linked') }}</label>
                                                            <input type="number" min="1" max="{{ \App\Livewire\Admin\Ccc\Conversations::BUBBLES_MAX }}" class="form-control form-control-sm" id="xmBdcLink{{ $i }}" wire:model.blur="bubbles.{{ $i }}.associee">
                                                        </div>
                                                        <div class="col-12 d-flex flex-wrap gap-3">
                                                            <div class="form-check"><input class="form-check-input" type="checkbox" id="xmBdcOver{{ $i }}" wire:model.live="bubbles.{{ $i }}.superpose"><label class="form-check-label small" for="xmBdcOver{{ $i }}">{{ __('admin_conversations_bd.fields.overlap') }}</label></div>
                                                            <div class="form-check"><input class="form-check-input" type="checkbox" id="xmBdcMask{{ $i }}" wire:model.live="bubbles.{{ $i }}.masque"><label class="form-check-label small" for="xmBdcMask{{ $i }}">{{ __('admin_conversations_bd.fields.masked') }}</label></div>
                                                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" wire:click="removeBubble({{ $i }})">{{ __('admin_conversations_bd.remove_bubble', ['n' => $i + 1]) }}</button>
                                                        </div>
                                                    </div>
                                                </fieldset>
                                            </li>
                                        @endforeach
                                    </ol>
                                    <div class="d-flex flex-wrap gap-2">
                                        @if (count($bubbles) < \App\Livewire\Admin\Ccc\Conversations::BUBBLES_MAX)
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addBubble"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_conversations_bd.add_bubble') }}</button>
                                        @endif
                                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="savePanel">{{ __('admin_conversations_bd.save_panel') }}</button>
                                    </div>
                                    <p class="form-text mt-2 mb-0">{{ __('admin_conversations_bd.positions_help') }}</p>
                                </form>
                            </div>
                        @else
                            <p class="small text-secondary mb-0">{{ __('admin_conversations_bd.pick_panel') }}</p>
                        @endif
                    </section>
                </div>
            </section>
        @endif
    @endif
</div>
