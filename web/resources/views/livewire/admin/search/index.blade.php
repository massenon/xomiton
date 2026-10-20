{{--
    Recherche globale (P10-08) : composant App\Livewire\Admin\Search\Index, gabarit admin ; reprend admin/recherche.php.
    Résultats groupés, six par groupe avec lien vers l'écran du groupe ; aucun texte ne s'appuie sur une colonne chiffrée
    (récits, notes, GPS, contacts). Le terme saisi n'est ni journalisé ni mémorisé côté serveur.
--}}
<div class="xm-admin-search">
    <form class="card xm-admin-card mb-4" role="search" wire:submit.prevent novalidate>
        <div class="card-body">
            <label class="form-label fw-semibold" for="xmAdminSearch">{{ __('admin_search.label') }}</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input class="form-control" type="search" id="xmAdminSearch" wire:model.live.debounce.400ms="q" maxlength="{{ \App\Application\Admin\Search\GlobalSearch::MAX_LENGTH }}"
                       placeholder="{{ __('admin_search.placeholder') }}" aria-describedby="xmAdminSearchHelp" autocomplete="off" enterkeyhint="search" autofocus>
                <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>{{ __('admin_search.submit') }}</button>
            </div>
            <div class="form-text" id="xmAdminSearchHelp">{{ __('admin_search.help', ['min' => $min]) }}</div>
        </div>
    </form>

    @if ($throttled)
        <x-alert type="warning">{{ __('admin_search.throttled') }}</x-alert>
    @elseif ($term === '')
        <div class="card xm-admin-card"><div class="card-body text-center text-secondary py-5">
            <i class="bi bi-search display-5" aria-hidden="true"></i>
            <p class="mt-2 mb-0" @if ($tooShort) role="alert" @endif>{{ $tooShort ? __('admin_search.too_short', ['min' => $min]) : __('admin_search.start', ['min' => $min]) }}</p>
        </div></div>
    @else
        <p class="text-secondary" role="status">{{ trans_choice('admin_search.summary', $total, ['count' => $total, 'term' => $term]) }}</p>
        <div class="row g-3">
            @foreach ($groups as $group)
                <div class="col-12 col-xl-6" wire:key="group-{{ $group['key'] }}">
                    <section class="card xm-admin-card h-100" aria-labelledby="xmSearchGroup-{{ $group['key'] }}">
                        <div class="card-header d-flex align-items-center gap-2">
                            <span class="icon-md rounded-circle bg-{{ $group['color'] }} bg-opacity-10 text-{{ $group['color'] }}"><i class="bi bi-{{ $group['icon'] }}" aria-hidden="true"></i></span>
                            <h2 class="h6 mb-0 flex-grow-1" id="xmSearchGroup-{{ $group['key'] }}">{{ __('admin_search.groups.'.$group['key']) }}</h2>
                            <x-badge color="neutral" soft>{{ $group['total'] }}</x-badge>
                        </div>
                        <ul class="list-group list-group-flush">
                            @foreach ($group['rows'] as $row)
                                <li class="list-group-item p-0">
                                    @if ($row['url'])
                                        <a class="list-group-item-action d-block px-3 py-2 text-decoration-none" href="{{ $row['url'] }}">
                                            <span class="d-block fw-semibold small text-break">{{ $row['title'] }}</span>
                                            <span class="d-block small text-secondary text-truncate">{{ $row['detail'] }}</span>
                                        </a>
                                    @else
                                        <div class="px-3 py-2"><span class="d-block fw-semibold small text-break">{{ $row['title'] }}</span><span class="d-block small text-secondary text-truncate">{{ $row['detail'] }}</span></div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($group['total'] > $perGroup && $group['url'])
                            <div class="card-footer text-center"><a class="small" href="{{ $group['url'] }}">{{ trans_choice('admin_search.see_all', $group['total'], ['count' => $group['total'], 'group' => __('admin_search.groups.'.$group['key'])]) }}</a></div>
                        @endif
                    </section>
                </div>
            @endforeach
            @if ($total === 0)
                <div class="col-12"><div class="card xm-admin-card"><div class="card-body text-center text-secondary py-5"><i class="bi bi-emoji-neutral display-5" aria-hidden="true"></i><p class="mt-2 mb-0">{{ __('admin_search.none') }}</p></div></div></div>
            @endif
        </div>
    @endif
</div>
