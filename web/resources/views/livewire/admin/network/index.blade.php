{{--
    Réseau social (P12-05) : composant App\Livewire\Admin\Network\Index, gabarit admin.
    Ordre : introduction · KPI · onglets (Groupes, Pages, Événements, Forums, Publications) · filtres · tableau · pied.
    Une page d'organisation n'est visible du public qu'une fois approuvée ; « En avant » place la page ou le groupe en tête de liste.
--}}
@php
    $tabMeta = [
        'groupes' => ['label' => __('admin_network.tabs.groupes'), 'icon' => 'people'],
        'pages' => ['label' => __('admin_network.tabs.pages'), 'icon' => 'flag'],
        'evenements' => ['label' => __('admin_network.tabs.evenements'), 'icon' => 'calendar-event'],
        'forums' => ['label' => __('admin_network.tabs.forums'), 'icon' => 'chat-square-text'],
        'publications' => ['label' => __('admin_network.tabs.publications'), 'icon' => 'card-text'],
    ];
    $counts = $this->counts;
    $tabs = [];
    foreach ($tabMeta as $key => $meta) {
        $tabs[$key] = $meta + (($key === 'pages' && $counts['pages'] > 0) ? ['count' => __('admin_network.tab_pending', ['count' => $counts['pages']])] : []);
    }
@endphp
<div class="xm-network-admin">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_network.intro') }}</p>

    <x-admin.kpi id="xmNetKpis" :items="$this->kpis" />

    <x-admin.tab-nav :tabs="$tabs" :current="$tab" :label="__('admin_network.tabs_label')" />

    <section class="card xm-admin-card" aria-labelledby="xmNetTitle">
        <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmNetTitle">{{ __('admin_network.tabs.'.$tab) }}</h2></div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmNetFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_network.'.$tab.'.search_placeholder')" :search-help="__('admin_network.search_help')">
                <div class="col-6 col-lg-3">
                    <label class="visually-hidden" for="xmNetState">{{ __('admin_network.columns.state') }}</label>
                    <select class="form-select form-select-sm" id="xmNetState" wire:model.live="state">
                        <option value="">{{ __('admin_network.states.all') }}</option>
                        <option value="visible">{{ __('admin_network.states.visible') }}</option>
                        <option value="hidden">{{ __('admin_network.states.hidden') }}</option>
                        @if ($tab === 'pages')<option value="pending">{{ __('admin_network.states.pending') }}</option>@endif
                    </select>
                </div>
            </x-admin.filters>
        </div>

        @if ($tab === 'groupes')
            <x-admin.table id="xmNetTable" :caption="__('admin_network.groupes.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'name', 'label' => __('admin_network.columns.group'), 'sortable' => true],
                ['key' => 'privacy', 'label' => __('admin_network.columns.privacy'), 'width' => '9rem'],
                ['key' => 'members', 'label' => __('admin_network.columns.members'), 'sortable' => true, 'class' => 'text-end', 'width' => '7rem'],
                ['key' => 'state', 'label' => __('admin_network.columns.state'), 'width' => '11rem'],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $group)
                    <tr wire:key="group-{{ $group->id }}">
                        <td data-label="{{ __('admin_network.columns.group') }}">
                            <div class="fw-semibold text-break">{{ $group->name }}</div>
                            <div class="small text-secondary">{{ $group->zone?->name ?? __('admin_network.no_zone') }} · {{ __('admin_network.owner', ['name' => $group->owner?->pseudo ?? '—']) }}</div>
                        </td>
                        <td data-label="{{ __('admin_network.columns.privacy') }}"><x-badge :color="$group->privacy === \App\Enums\GroupPrivacy::Private ? 'neutral' : 'info'" soft>{{ $group->privacy->label() }}</x-badge></td>
                        <td data-label="{{ __('admin_network.columns.members') }}" class="text-end">{{ $format->number((int) $group->members_count) }}</td>
                        <td data-label="{{ __('admin_network.columns.state') }}">
                            <div class="d-flex flex-wrap gap-1">
                                @if (! $group->is_active)<x-badge color="warning" soft icon="eye-slash">{{ __('admin_network.badges.hidden') }}</x-badge>@endif
                                @if ($group->is_featured)<x-badge color="warning" icon="star-fill">{{ __('admin_network.badges.featured') }}</x-badge>@endif
                            </div>
                        </td>
                        <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('groupes.show', $group->ulid) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('admin_network.actions.view') }}<span class="visually-hidden"> {{ $group->name }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                @if ($canCurate)
                                    <button type="button" @class(['btn btn-sm', 'btn-warning' => $group->is_featured, 'btn-outline-secondary' => ! $group->is_featured]) wire:click="feature('group', {{ $group->id }})" aria-pressed="{{ $group->is_featured ? 'true' : 'false' }}"><i class="bi bi-star{{ $group->is_featured ? '-fill' : '' }} me-1" aria-hidden="true"></i>{{ __('admin_network.actions.feature') }}<span class="visually-hidden"> {{ $group->name }}</span></button>
                                    @if ($group->is_active)
                                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="hide('group', {{ $group->id }})" wire:confirm="{{ __('admin_network.confirm.hide', ['name' => $group->name]) }}">{{ __('admin_network.actions.hide') }}<span class="visually-hidden"> {{ $group->name }}</span></button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-success" wire:click="show('group', {{ $group->id }})">{{ __('admin_network.actions.show') }}<span class="visually-hidden"> {{ $group->name }}</span></button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="remove('group', {{ $group->id }})" wire:confirm="{{ __('admin_network.confirm.delete', ['name' => $group->name]) }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_network.actions.delete') }} {{ $group->name }}</span></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="people" :filtered="$this->activeFilterCount() > 0" :title="__('admin_network.groupes.empty')" /></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmNetPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        @elseif ($tab === 'pages')
            <x-admin.table id="xmNetTable" :caption="__('admin_network.pages.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'name', 'label' => __('admin_network.columns.page'), 'sortable' => true],
                ['key' => 'category', 'label' => __('admin_network.columns.category'), 'width' => '10rem'],
                ['key' => 'followers', 'label' => __('admin_network.columns.followers'), 'sortable' => true, 'class' => 'text-end', 'width' => '8rem'],
                ['key' => 'state', 'label' => __('admin_network.columns.state'), 'width' => '12rem'],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $page)
                    <tr wire:key="page-{{ $page->id }}">
                        <td data-label="{{ __('admin_network.columns.page') }}">
                            <div class="fw-semibold text-break">{{ $page->name }}</div>
                            <div class="small text-secondary">{{ __('admin_network.owner', ['name' => $page->owner?->pseudo ?? '—']) }}</div>
                        </td>
                        <td data-label="{{ __('admin_network.columns.category') }}">{{ $page->category }}</td>
                        <td data-label="{{ __('admin_network.columns.followers') }}" class="text-end">{{ $format->number((int) $page->followers_count) }}</td>
                        <td data-label="{{ __('admin_network.columns.state') }}">
                            <div class="d-flex flex-wrap gap-1">
                                @if ($page->is_approved)<x-badge color="success" soft icon="check2-circle">{{ __('admin_network.badges.approved') }}</x-badge>@else<x-badge color="warning" soft icon="hourglass-split">{{ __('admin_network.badges.pending') }}</x-badge>@endif
                                @if (! $page->is_active)<x-badge color="warning" soft icon="eye-slash">{{ __('admin_network.badges.hidden') }}</x-badge>@endif
                                @if ($page->is_featured)<x-badge color="warning" icon="star-fill">{{ __('admin_network.badges.featured') }}</x-badge>@endif
                            </div>
                        </td>
                        <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('pages.show', $page->ulid) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('admin_network.actions.view') }}<span class="visually-hidden"> {{ $page->name }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                @if ($canCurate)
                                    @if ($page->is_approved)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="approve({{ $page->id }}, false)" wire:confirm="{{ __('admin_network.confirm.unapprove', ['name' => $page->name]) }}">{{ __('admin_network.actions.unapprove') }}<span class="visually-hidden"> {{ $page->name }}</span></button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $page->id }}, true)"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>{{ __('admin_network.actions.approve') }}<span class="visually-hidden"> {{ $page->name }}</span></button>
                                    @endif
                                    <button type="button" @class(['btn btn-sm', 'btn-warning' => $page->is_featured, 'btn-outline-secondary' => ! $page->is_featured]) wire:click="feature('page', {{ $page->id }})" aria-pressed="{{ $page->is_featured ? 'true' : 'false' }}"><i class="bi bi-star{{ $page->is_featured ? '-fill' : '' }} me-1" aria-hidden="true"></i>{{ __('admin_network.actions.feature') }}<span class="visually-hidden"> {{ $page->name }}</span></button>
                                    @if ($page->is_active)
                                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="hide('page', {{ $page->id }})" wire:confirm="{{ __('admin_network.confirm.hide', ['name' => $page->name]) }}">{{ __('admin_network.actions.hide') }}<span class="visually-hidden"> {{ $page->name }}</span></button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-success" wire:click="show('page', {{ $page->id }})">{{ __('admin_network.actions.show') }}<span class="visually-hidden"> {{ $page->name }}</span></button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="remove('page', {{ $page->id }})" wire:confirm="{{ __('admin_network.confirm.delete', ['name' => $page->name]) }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_network.actions.delete') }} {{ $page->name }}</span></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="flag" :filtered="$this->activeFilterCount() > 0" :title="__('admin_network.pages.empty')" /></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmNetPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        @elseif ($tab === 'evenements')
            <x-admin.table id="xmNetTable" :caption="__('admin_network.evenements.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'title', 'label' => __('admin_network.columns.event'), 'sortable' => true],
                ['key' => 'date', 'label' => __('admin_network.columns.date'), 'sortable' => true, 'width' => '10rem'],
                ['key' => 'going', 'label' => __('admin_network.columns.going'), 'sortable' => true, 'class' => 'text-end', 'width' => '8rem'],
                ['key' => 'state', 'label' => __('admin_network.columns.state'), 'width' => '9rem'],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $event)
                    @php($hidden = $event->status === \App\Enums\ContentStatus::Hidden)
                    <tr wire:key="event-{{ $event->id }}">
                        <td data-label="{{ __('admin_network.columns.event') }}">
                            <div class="fw-semibold text-break">{{ $event->title }}</div>
                            <div class="small text-secondary">{{ $event->place ?: ($event->is_online ? __('admin_network.online') : '—') }} · {{ $event->organizer_label ?: '—' }}</div>
                        </td>
                        <td data-label="{{ __('admin_network.columns.date') }}" class="text-nowrap small">{{ $format->tableDateTime($event->starts_at) }}</td>
                        <td data-label="{{ __('admin_network.columns.going') }}" class="text-end">{{ $format->number((int) $event->going_count) }}</td>
                        <td data-label="{{ __('admin_network.columns.state') }}">
                            <x-badge :color="$event->status->badge()" soft>{{ $event->status->label() }}</x-badge>
                        </td>
                        <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('evenements.show', $event->ulid) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('admin_network.actions.view') }}<span class="visually-hidden"> {{ $event->title }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                @if ($canCurate)
                                    @if ($hidden)
                                        <button type="button" class="btn btn-sm btn-outline-success" wire:click="show('event', {{ $event->id }})">{{ __('admin_network.actions.show') }}<span class="visually-hidden"> {{ $event->title }}</span></button>
                                    @elseif ($event->status === \App\Enums\ContentStatus::Published)
                                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="hide('event', {{ $event->id }})" wire:confirm="{{ __('admin_network.confirm.hide', ['name' => $event->title]) }}">{{ __('admin_network.actions.hide') }}<span class="visually-hidden"> {{ $event->title }}</span></button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="remove('event', {{ $event->id }})" wire:confirm="{{ __('admin_network.confirm.delete', ['name' => $event->title]) }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_network.actions.delete') }} {{ $event->title }}</span></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="calendar-event" :filtered="$this->activeFilterCount() > 0" :title="__('admin_network.evenements.empty')" /></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmNetPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        @elseif ($tab === 'forums')
            <x-admin.table id="xmNetTable" :caption="__('admin_network.forums.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'title', 'label' => __('admin_network.columns.topic'), 'sortable' => true],
                ['key' => 'replies', 'label' => __('admin_network.columns.replies'), 'sortable' => true, 'class' => 'text-end', 'width' => '8rem'],
                ['key' => 'created', 'label' => __('admin_network.columns.date'), 'sortable' => true, 'width' => '10rem'],
                ['key' => 'state', 'label' => __('admin_network.columns.state'), 'width' => '9rem'],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $topic)
                    @php($hidden = $topic->status === \App\Enums\PostStatus::Hidden)
                    <tr wire:key="topic-{{ $topic->id }}">
                        <td data-label="{{ __('admin_network.columns.topic') }}">
                            <div class="fw-semibold text-break">{{ $topic->title }}</div>
                            <div class="small text-secondary">{{ $topic->forum?->name ?? '—' }} · {{ __('admin_network.by', ['name' => $topic->authorLabel()]) }}</div>
                        </td>
                        <td data-label="{{ __('admin_network.columns.replies') }}" class="text-end">{{ $format->number((int) $topic->replies_count) }}</td>
                        <td data-label="{{ __('admin_network.columns.date') }}" class="text-nowrap small">{{ $format->tableDateTime($topic->created_at) }}</td>
                        <td data-label="{{ __('admin_network.columns.state') }}"><x-badge :color="$topic->status->badge()" soft>{{ $topic->status->label() }}</x-badge></td>
                        <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('forums.show', $topic->ulid) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('admin_network.actions.view') }}<span class="visually-hidden"> {{ $topic->title }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                @if ($hidden)
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="show('topic', {{ $topic->id }})">{{ __('admin_network.actions.show') }}<span class="visually-hidden"> {{ $topic->title }}</span></button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-warning" wire:click="hide('topic', {{ $topic->id }})" wire:confirm="{{ __('admin_network.confirm.hide', ['name' => $topic->title]) }}">{{ __('admin_network.actions.hide') }}<span class="visually-hidden"> {{ $topic->title }}</span></button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="remove('topic', {{ $topic->id }})" wire:confirm="{{ __('admin_network.confirm.delete', ['name' => $topic->title]) }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_network.actions.delete') }} {{ $topic->title }}</span></button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="chat-square-text" :filtered="$this->activeFilterCount() > 0" :title="__('admin_network.forums.empty')" /></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmNetPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        @else
            <x-admin.table id="xmNetTable" :caption="__('admin_network.publications.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'post', 'label' => __('admin_network.columns.post')],
                ['key' => 'created', 'label' => __('admin_network.columns.date'), 'sortable' => true, 'width' => '10rem'],
                ['key' => 'reports', 'label' => __('admin_network.columns.reports'), 'sortable' => true, 'class' => 'text-end', 'width' => '8rem'],
                ['key' => 'state', 'label' => __('admin_network.columns.state'), 'width' => '9rem'],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $post)
                    @php($hidden = $post->status === \App\Enums\PostStatus::Hidden)
                    <tr wire:key="post-{{ $post->id }}">
                        <td data-label="{{ __('admin_network.columns.post') }}">
                            <div class="text-break">{{ \Illuminate\Support\Str::limit($post->body, 160) }}</div>
                            <div class="small text-secondary mt-1">{{ __('admin_network.by', ['name' => $post->authorLabel()]) }} · {{ __('admin_network.likes_comments', ['likes' => (int) $post->likes_count, 'comments' => (int) $post->comments_count]) }}
                                @if ($post->group) · {{ $post->group->name }}@elseif ($post->page) · {{ $post->page->name }}@endif</div>
                        </td>
                        <td data-label="{{ __('admin_network.columns.date') }}" class="text-nowrap small">{{ $format->tableDateTime($post->created_at) }}</td>
                        <td data-label="{{ __('admin_network.columns.reports') }}" class="text-end">{{ $format->number((int) $post->reports_count) }}</td>
                        <td data-label="{{ __('admin_network.columns.state') }}"><x-badge :color="$post->status->badge()" soft>{{ $post->status->label() }}</x-badge></td>
                        <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('fil.show', $post->ulid) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('admin_network.actions.view') }}<span class="visually-hidden"> {{ __('admin_network.post_sr', ['id' => $post->id]) }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                @if ($hidden)
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="show('post', {{ $post->id }})">{{ __('admin_network.actions.show') }}<span class="visually-hidden"> {{ __('admin_network.post_sr', ['id' => $post->id]) }}</span></button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-warning" wire:click="hide('post', {{ $post->id }})" wire:confirm="{{ __('admin_network.confirm.hide_post') }}">{{ __('admin_network.actions.hide') }}<span class="visually-hidden"> {{ __('admin_network.post_sr', ['id' => $post->id]) }}</span></button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="remove('post', {{ $post->id }})" wire:confirm="{{ __('admin_network.confirm.delete_post') }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_network.actions.delete') }} {{ __('admin_network.post_sr', ['id' => $post->id]) }}</span></button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="card-text" :filtered="$this->activeFilterCount() > 0" :title="__('admin_network.publications.empty')" /></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmNetPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        @endif
    </section>
</div>
