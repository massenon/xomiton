{{--
    Liste publique des BD `/bd` (P19-04, BdIndexController) : BD publiées et publiques seulement (Q19). Indexable, vous.
    Variables : $comics (CccTool, `thematique` chargée), $breadcrumb, $seo.
--}}
<x-layouts.public :seo="$seo">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <x-breadcrumb :items="$breadcrumb" />
            <header class="vstack gap-2">
                <h1 class="h3 mb-0">{{ __('pages/bd.h1') }}</h1>
                <p class="lead fs-6 mb-0">{{ __('pages/bd.lead') }}</p>
                <p class="small text-secondary mb-0">{{ trans_choice('pages/bd.count', $comics->count(), ['count' => $comics->count()]) }} · <a href="{{ route('violences-numeriques') }}">{{ __('pages/violences_numeriques.hub_link') }}</a></p>
            </header>

            @if ($comics->isEmpty())
                <x-empty-state :title="__('pages/bd.empty')" icon="book" />
            @else
                <section aria-labelledby="bd-liste-titre">
                    <h2 class="visually-hidden" id="bd-liste-titre">{{ __('pages/bd.list_label') }}</h2>
                    <ul class="list-unstyled row g-3 mb-0">
                        @foreach ($comics as $comic)
                            <li class="col-sm-6 col-lg-4 col-xl-3">@include('public.bd.card', ['comic' => $comic, 'heading' => 'h3', 'priority' => $loop->first])</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="card border-primary" aria-labelledby="bd-aide-titre">
                <div class="card-body d-flex flex-column flex-md-row gap-3 align-items-md-center">
                    <h2 class="h6 mb-0 flex-grow-1" id="bd-aide-titre">{{ __('pages/bd.help_title') }}</h2>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-primary" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('pages/bd.help_report') }}</a>
                        <a class="btn btn-sm btn-primary-soft" href="{{ route('sos') }}"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('pages/bd.help_sos') }}</a>
                        <a class="btn btn-sm btn-primary-soft" href="{{ route('urgences') }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('pages/bd.help_emergency') }}</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-layouts.public>
