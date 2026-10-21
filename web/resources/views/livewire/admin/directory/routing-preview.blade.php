{{--
    Aperçu du routage des alertes (P11-04) : composant App\Livewire\Admin\Directory\RoutingPreview. Règle RG-04 : 1. le rôle
    ciblé par le besoin, dans le département de l'alerte ; 2. à défaut, le point focal du département ; 3. le point focal
    national ; 4. le premier contact actif. Le badge « repli » signale un contact qui n'est pas celui du rôle demandé.
--}}
<section class="card xm-admin-card mb-4" aria-labelledby="xmRoutingTitle">
    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmRoutingTitle"><i class="bi bi-signpost-split me-2" aria-hidden="true"></i>{{ __('admin_data.directory.routing.title') }}</h2></div>
    <div class="card-body">
        <p class="small text-secondary xm-prose">{{ __('admin_data.directory.routing.rule') }}</p>

        <div class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1" for="xmRoutingNeed">{{ __('admin_data.directory.routing.need') }}</label>
                <select class="form-select form-select-sm" id="xmRoutingNeed" wire:model.live="need">
                    @foreach ($needOptions as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1" for="xmRoutingZone">{{ __('admin_data.directory.routing.zone') }}</label>
                <select class="form-select form-select-sm" id="xmRoutingZone" wire:model.live="zone">
                    @foreach ($zones as $zone)<option value="{{ $zone['id'] }}" @selected($selectedZone === $zone['id'])>{{ $zone['name'] }}</option>@endforeach
                </select>
            </div>
        </div>

        @php($decision = $explanation->decision)
        @php($target = $decision->contactId ? $contacts->get($decision->contactId) : null)
        <div class="alert alert-info d-flex gap-3 align-items-center" role="status" aria-live="polite">
            <i class="bi bi-send-check fs-3" aria-hidden="true"></i>
            <div>
                @if ($target)
                    <strong>{{ $target->name }}</strong> · {{ $target->role->label() }} ({{ $target->zone?->name ?? __('admin_data.directory.national') }})
                    @if ($decision->isFallback())<x-badge color="warning" class="ms-1">{{ __('admin_data.directory.routing.fallback') }}</x-badge>@endif
                    <br><span class="small">{{ $decision->fallback?->label() }}@if ($target->phone) — {{ $target->phone }}@endif</span>
                @else
                    {{ __('admin_data.directory.routing.none') }}
                @endif
            </div>
        </div>

        <h3 class="h6">{{ __('admin_data.directory.routing.levels') }}</h3>
        <ol class="list-group mb-4">
            @foreach ($explanation->levels as $level)
                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2 @if ($level['retained']) list-group-item-success @endif">
                    <span>
                        <span class="fw-semibold me-1">{{ $level['level'] }}.</span>{{ $level['fallback']->label() }}
                        @if ($level['skipped'])<span class="text-secondary small">— {{ __('admin_data.directory.routing.skipped') }}</span>
                        @elseif ($level['matched'])<span class="small">— {{ $contacts->get($level['matched'])?->name ?? '#'.$level['matched'] }}</span>
                        @else<span class="text-secondary small">— {{ __('admin_data.directory.routing.nobody') }}</span>@endif
                    </span>
                    @if ($level['retained'])<x-badge color="success" icon="check2">{{ __('admin_data.directory.routing.retained') }}</x-badge>@endif
                </li>
            @endforeach
        </ol>

        <h3 class="h6">{{ __('admin_data.directory.routing.matrix') }}</h3>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="xmRoutingMatrix">
                <caption class="visually-hidden">{{ __('admin_data.directory.routing.matrix_caption') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin_data.directory.routing.zone') }}</th>
                        @foreach ($columns as $column)<th scope="col">{{ $columnLabels[$column] }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matrix as $line)
                        <tr wire:key="routing-{{ $line['zone']['id'] }}">
                            <th scope="row" class="fw-semibold">{{ $line['zone']['name'] }}</th>
                            @foreach ($columns as $column)
                                @php($cell = $line['cells'][$column])
                                <td class="small">
                                    @if ($cell->contactId)
                                        {{ $contacts->get($cell->contactId)?->name ?? '#'.$cell->contactId }}
                                        @if ($cell->isFallback())<x-badge color="warning" title="{{ $cell->fallback?->label() }}">{{ __('admin_data.directory.routing.fallback') }}</x-badge>@endif
                                    @else
                                        <span class="text-danger">—<span class="visually-hidden">{{ __('admin_data.directory.routing.none') }}</span></span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
