{{--
    Contenu (P12-03) : composant App\Livewire\Admin\Content\Index, gabarit admin.
    Ordre : introduction · KPI · onglets (Ressources, Thématiques, FAQ, Catégories) · filtres · tableau · pied · 4 modales toujours montées.
    Une ligne = un contenu ; « Publier » / « Brouillon » agit sans rouvrir la modale ; la publication d'une vidéo ou d'un
    audio exige sous-titres et transcription (ACCESSIBILITE.md §5).
--}}
@php
    $statusBadge = fn ($status) => $status === \App\Enums\ContentStatus::Published ? ['success', __('admin_content.status.published')] : [$status === \App\Enums\ContentStatus::Hidden ? 'warning' : 'neutral', $status === \App\Enums\ContentStatus::Hidden ? __('admin_content.status.hidden') : __('admin_content.status.draft')];
    $tabMeta = [
        'ressources' => ['label' => __('admin_content.tabs.ressources'), 'icon' => 'folder2-open'],
        'thematiques' => ['label' => __('admin_content.tabs.thematiques'), 'icon' => 'journal-text'],
        'faq' => ['label' => __('admin_content.tabs.faq'), 'icon' => 'question-circle'],
        'categories' => ['label' => __('admin_content.tabs.categories'), 'icon' => 'tags'],
    ];
    $visibleTabs = array_intersect_key($tabMeta, array_flip($tabs));
    $categoryKinds = [\App\Models\Category::KIND_THEMATIQUE, \App\Models\Category::KIND_RESOURCE, \App\Models\Category::KIND_FAQ, \App\Models\Category::KIND_EVENT];
@endphp
<div class="xm-content-admin">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_content.intro') }}</p>

    <x-admin.kpi id="xmContentKpis" :items="$this->kpis" />

    <x-admin.tab-nav :tabs="$visibleTabs" :current="$tab" :label="__('admin_content.tabs_label')" />

    <section class="card xm-admin-card" aria-labelledby="xmContentTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmContentTitle">{{ __('admin_content.'.$tab.'.title') }}</h2>
            <div class="d-flex flex-wrap gap-2">
                @if ($tab === 'ressources')
                    @can('create', \App\Models\Resource::class)<button type="button" class="btn btn-primary btn-sm" wire:click="newResource"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_content.resources.new') }}</button>@endcan
                @elseif ($tab === 'thematiques')
                    @can('create', \App\Models\Thematique::class)<button type="button" class="btn btn-primary btn-sm" wire:click="newThematique"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_content.thematiques.new') }}</button>@endcan
                @elseif ($tab === 'faq')
                    @can('create', \App\Models\Faq::class)<button type="button" class="btn btn-primary btn-sm" wire:click="newFaq"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_content.faqs.new') }}</button>@endcan
                @else
                    <button type="button" class="btn btn-primary btn-sm" wire:click="newCategory"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_content.categories.new') }}</button>
                @endif
            </div>
        </div>

        @if ($tab === 'faq')
            <div class="card-body border-bottom">
                <p class="small mb-1"><i class="bi bi-house me-1" aria-hidden="true"></i>{{ trans_choice('admin_content.faqs.home_count', $this->faqHealth['home'], ['count' => $this->faqHealth['home']]) }}
                    @unless ($this->faqHealth['home_ok'])<x-badge color="warning" soft icon="exclamation-triangle">{{ __('admin_content.faqs.home_bounds') }}</x-badge>@endunless</p>
                @if ($this->faqHealth['categories'] !== [])
                    <p class="small text-secondary mb-0">{{ __('admin_content.faqs.bounds_help') }}
                        @foreach ($this->faqHealth['categories'] as $row)<x-badge color="neutral" soft>{{ $row['label'] }} · {{ $row['count'] }}</x-badge> @endforeach</p>
                @endif
            </div>
        @endif

        <div class="card-body border-bottom">
            <x-admin.filters id="xmContentFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_content.'.$tab.'.search_placeholder')" :search-help="__('admin_content.search_help')">
                @if (in_array($tab, ['ressources', 'thematiques', 'faq'], true))
                    <div class="col-6 col-lg-2">
                        <label class="visually-hidden" for="xmContentStatus">{{ __('admin_content.columns.status') }}</label>
                        <select class="form-select form-select-sm" id="xmContentStatus" wire:model.live="status">
                            <option value="">{{ __('admin_content.all_statuses') }}</option>
                            <option value="publie">{{ __('admin_content.status.published') }}</option>
                            <option value="brouillon">{{ $tab === 'faq' ? __('admin_content.status.hidden') : __('admin_content.status.draft') }}</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-3">
                        <label class="visually-hidden" for="xmContentCategory">{{ __('admin_content.columns.category') }}</label>
                        <select class="form-select form-select-sm" id="xmContentCategory" wire:model.live="category">
                            <option value="">{{ __('admin_content.all_categories') }}</option>
                            @foreach ($this->categoryOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($tab === 'ressources')
                    <div class="col-6 col-lg-2">
                        <label class="visually-hidden" for="xmContentKind">{{ __('admin_content.columns.kind') }}</label>
                        <select class="form-select form-select-sm" id="xmContentKind" wire:model.live="kind">
                            <option value="">{{ __('admin_content.all_kinds') }}</option>
                            @foreach ($kinds as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                        </select>
                    </div>
                @elseif ($tab === 'categories')
                    <div class="col-6 col-lg-3">
                        <label class="visually-hidden" for="xmContentCategoryKind">{{ __('admin_content.categories.fields.kind') }}</label>
                        <select class="form-select form-select-sm" id="xmContentCategoryKind" wire:model.live="kind">
                            <option value="">{{ __('admin_content.all_category_kinds') }}</option>
                            @foreach ($categoryKinds as $value)<option value="{{ $value }}">{{ __('admin_content.categories.kinds.'.$value) }}</option>@endforeach
                        </select>
                    </div>
                @endif
            </x-admin.filters>
        </div>

        @if ($tab === 'ressources')
            <x-admin.table id="xmContentTable" :caption="__('admin_content.resources.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'title', 'label' => __('admin_content.columns.title'), 'sortable' => true],
                ['key' => 'status', 'label' => __('admin_content.columns.status'), 'width' => '8rem'],
                ['key' => 'views', 'label' => __('admin_content.columns.views'), 'sortable' => true, 'class' => 'text-end', 'width' => '6rem'],
                ['key' => 'updated', 'label' => __('admin_content.columns.updated'), 'sortable' => true, 'width' => '9rem'],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $resource)
                    @php([$badgeColor, $badgeLabel] = $statusBadge($resource->status))
                    <tr wire:key="resource-{{ $resource->id }}">
                        <td data-label="{{ __('admin_content.columns.title') }}">
                            <div class="fw-semibold text-break">{{ $resource->title }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <x-badge :color="$resource->kind->badge()" soft :icon="str_replace('bi-', '', $resource->kind->icon())">{{ $resource->kind->label() }}</x-badge>
                                @if ($resource->category)<x-badge color="neutral" soft>{{ $resource->category->label }}</x-badge>@endif
                                @if ($resource->kind->hasFile() && ! $resource->isDownloadable())<x-badge color="warning" soft icon="exclamation-triangle">{{ __('admin_content.resources.no_file') }}</x-badge>@endif
                                @if (in_array($resource->kind, [\App\Enums\ResourceKind::Video, \App\Enums\ResourceKind::Audio], true) && ((string) $resource->subtitles_path === '' || trim((string) $resource->transcript) === ''))
                                    <x-badge color="warning" soft icon="badge-cc">{{ __('admin_content.resources.no_subtitles') }}</x-badge>
                                @endif
                            </div>
                            <div class="small text-secondary text-break mt-1"><code>{{ $resource->slug }}</code></div>
                        </td>
                        <td data-label="{{ __('admin_content.columns.status') }}"><x-badge :color="$badgeColor" soft>{{ $badgeLabel }}</x-badge></td>
                        <td data-label="{{ __('admin_content.columns.views') }}" class="text-end">{{ $format->number((int) $resource->views_count) }}</td>
                        <td data-label="{{ __('admin_content.columns.updated') }}" class="text-nowrap"><time class="small" datetime="{{ $resource->updated_at?->toIso8601String() }}">{{ $format->tableDateTime($resource->updated_at) }}</time></td>
                        <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editResource({{ $resource->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_content.actions.edit') }}<span class="visually-hidden"> {{ $resource->title }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleResource({{ $resource->id }})" wire:loading.attr="disabled" wire:target="toggleResource({{ $resource->id }})">{{ $resource->status === \App\Enums\ContentStatus::Published ? __('admin_content.actions.unpublish') : __('admin_content.actions.publish') }}<span class="visually-hidden"> {{ $resource->title }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteResource({{ $resource->id }})" wire:confirm="{{ __('admin_content.resources.confirm_delete', ['title' => $resource->title]) }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_content.actions.delete') }} {{ $resource->title }}</span></button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="folder2-open" :filtered="$this->activeFilterCount() > 0" :title="__('admin_content.resources.empty')">{{ __('admin_content.resources.empty_text') }}</x-admin.empty></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmContentPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        @elseif ($tab === 'thematiques')
            <x-admin.table id="xmContentTable" :caption="__('admin_content.thematiques.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'title', 'label' => __('admin_content.columns.title'), 'sortable' => true],
                ['key' => 'status', 'label' => __('admin_content.columns.status'), 'width' => '8rem'],
                ['key' => 'views', 'label' => __('admin_content.columns.views'), 'sortable' => true, 'class' => 'text-end', 'width' => '6rem'],
                ['key' => 'updated', 'label' => __('admin_content.columns.updated'), 'sortable' => true, 'width' => '9rem'],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $thematique)
                    @php([$badgeColor, $badgeLabel] = $statusBadge($thematique->status))
                    <tr wire:key="thematique-{{ $thematique->id }}">
                        <td data-label="{{ __('admin_content.columns.title') }}">
                            <div class="fw-semibold text-break">{{ $thematique->title }}</div>
                            <div class="small text-secondary text-break">{{ $thematique->summary }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                @if ($thematique->category)<x-badge color="neutral" soft>{{ $thematique->category->label }}</x-badge>@endif
                                <x-badge color="neutral" soft icon="clock">{{ __('admin_content.thematiques.minutes', ['count' => $thematique->reading_minutes]) }}</x-badge>
                                <code class="small">{{ $thematique->slug }}</code>
                            </div>
                        </td>
                        <td data-label="{{ __('admin_content.columns.status') }}"><x-badge :color="$badgeColor" soft>{{ $badgeLabel }}</x-badge></td>
                        <td data-label="{{ __('admin_content.columns.views') }}" class="text-end">{{ $format->number((int) $thematique->views_count) }}</td>
                        <td data-label="{{ __('admin_content.columns.updated') }}" class="text-nowrap"><time class="small" datetime="{{ $thematique->updated_at?->toIso8601String() }}">{{ $format->tableDateTime($thematique->updated_at) }}</time></td>
                        <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editThematique({{ $thematique->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_content.actions.edit') }}<span class="visually-hidden"> {{ $thematique->title }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleThematique({{ $thematique->id }})" wire:loading.attr="disabled" wire:target="toggleThematique({{ $thematique->id }})">{{ $thematique->status === \App\Enums\ContentStatus::Published ? __('admin_content.actions.unpublish') : __('admin_content.actions.publish') }}<span class="visually-hidden"> {{ $thematique->title }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteThematique({{ $thematique->id }})" wire:confirm="{{ __('admin_content.thematiques.confirm_delete', ['title' => $thematique->title]) }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_content.actions.delete') }} {{ $thematique->title }}</span></button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="journal-text" :filtered="$this->activeFilterCount() > 0" :title="__('admin_content.thematiques.empty')">{{ __('admin_content.thematiques.empty_text') }}</x-admin.empty></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmContentPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        @elseif ($tab === 'faq')
            <x-admin.table id="xmContentTable" :caption="__('admin_content.faqs.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'question', 'label' => __('admin_content.faqs.fields.question'), 'sortable' => true],
                ['key' => 'status', 'label' => __('admin_content.columns.status'), 'width' => '8rem'],
                ['key' => 'helpful', 'label' => __('admin_content.faqs.helpful'), 'class' => 'text-end', 'width' => '8rem'],
                ['key' => 'position', 'label' => __('admin_content.columns.order'), 'sortable' => true, 'width' => '7rem'],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $faq)
                    <tr wire:key="faq-{{ $faq->id }}">
                        <td data-label="{{ __('admin_content.faqs.fields.question') }}">
                            <div class="fw-semibold text-break">{{ $faq->question }}</div>
                            <div class="small text-secondary text-break">{{ \Illuminate\Support\Str::limit($faq->answer, 140) }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                @if ($faq->category)<x-badge color="neutral" soft>{{ $faq->category->label }}</x-badge>@endif
                                @if ($faq->show_on_home)<x-badge color="info" soft icon="house">{{ __('admin_content.faqs.on_home') }}</x-badge>@endif
                            </div>
                        </td>
                        <td data-label="{{ __('admin_content.columns.status') }}"><x-badge :color="$faq->is_published ? 'success' : 'neutral'" soft>{{ $faq->is_published ? __('admin_content.status.published') : __('admin_content.status.hidden') }}</x-badge></td>
                        <td data-label="{{ __('admin_content.faqs.helpful') }}" class="text-end small">{{ __('admin_content.faqs.helpful_counts', ['yes' => $format->number((int) $faq->helpful_count), 'no' => $format->number((int) $faq->unhelpful_count)]) }}</td>
                        <td data-label="{{ __('admin_content.columns.order') }}">
                            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('admin_content.faqs.reorder', ['question' => $faq->question]) }}">
                                <button type="button" class="btn btn-outline-secondary" wire:click="moveFaq({{ $faq->id }}, 'up')"><i class="bi bi-arrow-up" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_content.actions.up') }}</span></button>
                                <button type="button" class="btn btn-outline-secondary" wire:click="moveFaq({{ $faq->id }}, 'down')"><i class="bi bi-arrow-down" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_content.actions.down') }}</span></button>
                            </div>
                        </td>
                        <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editFaq({{ $faq->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_content.actions.edit') }}<span class="visually-hidden"> {{ $faq->question }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleFaq({{ $faq->id }})">{{ $faq->is_published ? __('admin_content.actions.hide') : __('admin_content.actions.publish') }}<span class="visually-hidden"> {{ $faq->question }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteFaq({{ $faq->id }})" wire:confirm="{{ __('admin_content.faqs.confirm_delete') }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_content.actions.delete') }} {{ $faq->question }}</span></button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="question-circle" :filtered="$this->activeFilterCount() > 0" :title="__('admin_content.faqs.empty')">{{ __('admin_content.faqs.empty_text') }}</x-admin.empty></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmContentPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        @else
            <x-admin.table id="xmContentTable" :caption="__('admin_content.categories.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'label', 'label' => __('admin_content.categories.fields.label'), 'sortable' => true],
                ['key' => 'kind', 'label' => __('admin_content.categories.fields.kind')],
                ['key' => 'usage', 'label' => __('admin_content.categories.usage'), 'class' => 'text-end', 'width' => '7rem'],
                ['key' => 'position', 'label' => __('admin_content.columns.order'), 'sortable' => true, 'width' => '7rem'],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td data-label="{{ __('admin_content.categories.fields.label') }}">
                            <div class="d-flex gap-2 align-items-center">
                                @if ($category->icon)<i class="bi {{ $category->icon }} fs-5" aria-hidden="true"></i>@endif
                                <div><div class="fw-semibold">{{ $category->label }}</div><code class="small">{{ $category->slug }}</code>
                                    @unless ($category->is_active)<x-badge color="neutral" soft>{{ __('admin_content.categories.inactive') }}</x-badge>@endunless</div>
                            </div>
                        </td>
                        <td data-label="{{ __('admin_content.categories.fields.kind') }}"><x-badge color="neutral" soft>{{ __('admin_content.categories.kinds.'.$category->kind) }}</x-badge></td>
                        <td data-label="{{ __('admin_content.categories.usage') }}" class="text-end">{{ $format->number($usage[$category->id] ?? 0) }}</td>
                        <td data-label="{{ __('admin_content.columns.order') }}">
                            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('admin_content.categories.reorder', ['label' => $category->label]) }}">
                                <button type="button" class="btn btn-outline-secondary" wire:click="moveCategory({{ $category->id }}, 'up')"><i class="bi bi-arrow-up" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_content.actions.up') }}</span></button>
                                <button type="button" class="btn btn-outline-secondary" wire:click="moveCategory({{ $category->id }}, 'down')"><i class="bi bi-arrow-down" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_content.actions.down') }}</span></button>
                            </div>
                        </td>
                        <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editCategory({{ $category->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_content.actions.edit') }}<span class="visually-hidden"> {{ $category->label }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleCategory({{ $category->id }})">{{ $category->is_active ? __('admin_content.categories.deactivate') : __('admin_content.categories.activate') }}<span class="visually-hidden"> {{ $category->label }}</span></button>
                                @if (($usage[$category->id] ?? 0) === 0)
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteCategory({{ $category->id }})" wire:confirm="{{ __('admin_content.categories.confirm_delete', ['label' => $category->label]) }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_content.actions.delete') }} {{ $category->label }}</span></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="tags" :filtered="$this->activeFilterCount() > 0" :title="__('admin_content.categories.empty')">{{ __('admin_content.categories.empty_text') }}</x-admin.empty></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmContentPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        @endif
    </section>

    {{-- Modales toujours montées (jamais derrière un @if). --}}
    @include('livewire.admin.content.partials.resource-modal')
    @include('livewire.admin.content.partials.thematique-modal')
    @include('livewire.admin.content.partials.faq-modal')
    @include('livewire.admin.content.partials.category-modal')
</div>
