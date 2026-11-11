{{--
    Référentiels (décision client du 11/11/2026) : composant App\Livewire\Admin\Referentials\Index, gabarit admin.
    Ordre : introduction · onglets (Zones et communes, Types de violence, Besoins, Populations) · carte (titre, actions) ·
    [onglet Zones : choix du pays, ou retour à la liste quand les communes d'une zone sont ouvertes] · tableau · modale
    toujours montée. Une ligne = un élément ; « Monter » / « Descendre » sont de vrais boutons (clavier) et l'annonce du
    nouvel ordre passe par le toast (aria-live) ; la suppression demande confirmation seulement quand l'élément n'est utilisé
    nulle part, sinon le serveur refuse et propose de le désactiver.
--}}
@php
    $tabMeta = [
        'zones' => ['label' => __('admin_referentials.tabs.zones'), 'icon' => 'geo-alt', 'count' => $counts['zones']],
        'violence_types' => ['label' => __('admin_referentials.tabs.violence_types'), 'icon' => 'exclamation-octagon', 'count' => $counts['violence_types']],
        'needs' => ['label' => __('admin_referentials.tabs.needs'), 'icon' => 'life-preserver', 'count' => $counts['needs']],
        'populations' => ['label' => __('admin_referentials.tabs.populations'), 'icon' => 'people', 'count' => $counts['populations']],
    ];
    $countryName = \App\Application\Shared\Countries::name($country) ?? $country;
    $titleKey = $kind === 'commune' ? 'communes' : $kind;
    $columns = match ($kind) {
        'zones' => [
            ['key' => 'label', 'label' => __('admin_referentials.columns.zone')],
            ['key' => 'communes', 'label' => __('admin_referentials.columns.communes'), 'class' => 'text-end', 'width' => '8rem'],
        ],
        'commune' => [
            ['key' => 'label', 'label' => __('admin_referentials.columns.commune')],
        ],
        'violence_types' => [
            ['key' => 'label', 'label' => __('admin_referentials.columns.violence_type')],
            ['key' => 'text', 'label' => __('admin_referentials.columns.description')],
        ],
        'needs' => [
            ['key' => 'label', 'label' => __('admin_referentials.columns.need')],
            ['key' => 'role', 'label' => __('admin_referentials.columns.role')],
        ],
        default => [
            ['key' => 'label', 'label' => __('admin_referentials.columns.population')],
        ],
    };
    $columns = [
        ...$columns,
        ['key' => 'usage', 'label' => __('admin_referentials.columns.usage'), 'class' => 'text-end', 'width' => '7rem'],
        ['key' => 'order', 'label' => __('admin_referentials.columns.order'), 'width' => '7rem'],
        ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
    ];
@endphp
<div class="xm-referentials-admin">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_referentials.intro') }}</p>

    <x-admin.tab-nav :tabs="$tabMeta" :current="$tab" :label="__('admin_referentials.tabs_label')" />

    <section class="card xm-admin-card" aria-labelledby="xmReferentialsTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmReferentialsTitle">
                @if ($kind === 'commune'){{ __('admin_referentials.communes.title', ['zone' => $currentZone?->name]) }}@else{{ __('admin_referentials.'.$titleKey.'.title') }}@endif
            </h2>
            <div class="d-flex flex-wrap gap-2">
                @if ($kind === 'commune')
                    <button type="button" class="btn btn-light border btn-sm" wire:click="closeZone"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('admin_referentials.communes.back', ['country' => $countryName]) }}</button>
                @endif
                <button type="button" class="btn btn-primary btn-sm" wire:click="create"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_referentials.'.$titleKey.'.new') }}</button>
            </div>
        </div>

        @if ($kind === 'zones')
            <div class="card-body border-bottom">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label small fw-semibold" for="xmReferentialsCountry">{{ __('admin_referentials.country') }}</label>
                        <select class="form-select form-select-sm" id="xmReferentialsCountry" wire:model.live="country" aria-describedby="xmReferentialsCountryHelp">
                            @if ($countries['with'] !== [])
                                <optgroup label="{{ __('admin_referentials.countries_with_zones') }}">
                                    @foreach ($countries['with'] as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }} ({{ $option['count'] }})</option>@endforeach
                                </optgroup>
                            @endif
                            <optgroup label="{{ __('admin_referentials.countries_other') }}">
                                @foreach ($countries['others'] as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-12 col-lg-8"><p class="small text-secondary mb-1" id="xmReferentialsCountryHelp">{{ __('admin_referentials.country_help') }}</p></div>
                </div>
            </div>
        @elseif ($kind === 'commune')
            <div class="card-body border-bottom">
                <p class="small text-secondary mb-0">{{ __('admin_referentials.communes.help', ['zone' => $currentZone?->name, 'country' => $countryName]) }}</p>
            </div>
        @else
            <div class="card-body border-bottom"><p class="small text-secondary mb-0">{{ __('admin_referentials.'.$kind.'.help') }}</p></div>
        @endif

        <x-admin.table id="xmReferentialsTable" :caption="$kind === 'commune' ? __('admin_referentials.communes.caption', ['zone' => $currentZone?->name]) : __('admin_referentials.'.$titleKey.'.caption')" :empty="$rows === []" :columns="$columns">
            @foreach ($rows as $row)
                @php
                    $inUse = $row['usage'] > 0 || $row['communes'] > 0;
                    $deleteConfirm = $inUse ? null : __('admin_referentials.confirm_delete', ['label' => $row['label']]);
                @endphp
                <tr wire:key="referential-{{ $kind }}-{{ $row['id'] }}">
                    <td data-label="{{ $columns[0]['label'] }}">
                        <div class="d-flex gap-2 align-items-center">
                            @if ($row['icon'])<i class="bi {{ $row['icon'] }} fs-5" aria-hidden="true"></i>@endif
                            <div>
                                <div class="fw-semibold">{{ $row['label'] }}</div>
                                <code class="small">{{ $row['slug'] }}</code>
                                @unless ($row['active'])<x-badge color="neutral" soft>{{ __('admin_referentials.inactive') }}</x-badge>@endunless
                            </div>
                        </div>
                    </td>
                    @if ($kind === 'zones')
                        <td data-label="{{ __('admin_referentials.columns.communes') }}" class="text-end">{{ $row['communes'] }}</td>
                    @elseif ($kind === 'violence_types')
                        <td data-label="{{ __('admin_referentials.columns.description') }}" class="small">{{ $row['text'] }}</td>
                    @elseif ($kind === 'needs')
                        <td data-label="{{ __('admin_referentials.columns.role') }}">{{ $row['role'] ?? __('admin_referentials.needs.no_role') }}</td>
                    @endif
                    <td data-label="{{ __('admin_referentials.columns.usage') }}" class="text-end">{{ $row['usage'] }}</td>
                    <td data-label="{{ __('admin_referentials.columns.order') }}">
                        <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('admin_referentials.reorder', ['label' => $row['label']]) }}">
                            <button type="button" class="btn btn-outline-secondary" wire:click="move({{ $row['id'] }}, 'up')" @disabled($loop->first)><i class="bi bi-arrow-up" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_referentials.up') }} {{ $row['label'] }}</span></button>
                            <button type="button" class="btn btn-outline-secondary" wire:click="move({{ $row['id'] }}, 'down')" @disabled($loop->last)><i class="bi bi-arrow-down" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_referentials.down') }} {{ $row['label'] }}</span></button>
                        </div>
                    </td>
                    <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            @if ($kind === 'zones')
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openZone({{ $row['id'] }})"><i class="bi bi-list-ul me-1" aria-hidden="true"></i>{{ __('admin_referentials.zones.manage_communes') }}<span class="visually-hidden"> {{ $row['label'] }}</span></button>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $row['id'] }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_referentials.edit') }}<span class="visually-hidden"> {{ $row['label'] }}</span></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggle({{ $row['id'] }})">{{ $row['active'] ? __('admin_referentials.deactivate') : __('admin_referentials.activate') }}<span class="visually-hidden"> {{ $row['label'] }}</span></button>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $row['id'] }})" @if ($deleteConfirm) wire:confirm="{{ $deleteConfirm }}" @endif><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_referentials.delete') }} {{ $row['label'] }}</span></button>
                        </div>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState><x-admin.empty icon="geo-alt" :title="__('admin_referentials.'.$titleKey.'.empty')">{{ __('admin_referentials.'.$titleKey.'.empty_text') }}</x-admin.empty></x-slot:emptyState>
        </x-admin.table>
    </section>

    {{-- Modale toujours montée (jamais derrière un @if). --}}
    @include('livewire.admin.referentials.partials.modal')
</div>
