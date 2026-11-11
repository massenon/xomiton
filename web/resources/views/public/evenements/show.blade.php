{{--
    Fiche publique `/evenements/{ulid}` (P8-04, Q35 a ; EventsPageController::show pour un·e visiteur·se d'un événement
    public ; prototype event-details.php). Gabarit public, registre vous, indexable, JSON-LD `Event` (ville seulement).
    Fil d'Ariane Accueil › Événements › titre ; visuel, catégorie, h1, date · heure · ville ou « En ligne », description,
    organisation (page, groupe ou « Équipe Xomiton », **jamais le pseudo d'un compte** : page indexée, Q77) et charte ;
    l'adresse exacte et le lien de connexion ne sont **jamais** publiés : encart « Participer » (connexion avec retour sur
    la fiche, inscription ; masqué pour un compte déjà connecté) ; « Partager » révélé par JavaScript (public/share.js).
    Colonne : autres événements publics. Variables : $event, $others, $organizer, $loginUrl, $breadcrumb, $fmt, $seo.
--}}
@php
    $cover = \App\Support\MediaUrl::resolve($event->image_path, \App\Models\Event::DEFAULT_COVER);
    $location = \App\Http\Resources\Events\EventResource::locationLabel($event);
@endphp
<x-layouts.public :seo="$seo">
    <x-slot:head>
        <link rel="preload" as="image" href="{{ $cover }}" fetchpriority="high">
    </x-slot:head>

    <div class="container py-4 py-sm-5">
        <div class="row g-4">
            <div class="col-lg-8 vstack gap-4">
                <x-breadcrumb :items="$breadcrumb" />
                <article class="card overflow-hidden" aria-labelledby="evenement-titre">
                    <div class="xm-ratio-16x9"><x-img class="xm-img-cover" :src="$cover" :alt="__('pages/evenements.image_alt', ['title' => $event->title])" width="1200" height="675" priority /></div>
                    <div class="card-body p-4">
                        @if ($event->category)
                            <span class="xm-chip mb-2">{{ $event->category->label }}</span>
                        @endif
                        <h1 class="h3" id="evenement-titre">{{ $event->title }}</h1>
                        <ul class="nav nav-divider small mb-3">
                            <li class="nav-item"><time datetime="{{ $event->starts_at->toIso8601String() }}"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ $fmt->longDateWithDay($event->starts_at) }}</time></li>
                            <li class="nav-item"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ $fmt->time($event->starts_at) }}@if ($event->ends_at) – {{ $fmt->time($event->ends_at) }}@endif</li>
                            <li class="nav-item"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $location }}</li>
                        </ul>
                        @if (! $event->isUpcoming())
                            <p><span class="badge bg-secondary">{{ __('pages/evenements.past_badge') }}</span></p>
                        @endif
                        <h2 class="h6">{{ __('pages/evenements.description') }}</h2>
                        <p>{!! nl2br(e($event->description ?: __('pages/evenements.no_description'))) !!}</p>
                        <p class="small text-secondary">{{ __('pages/evenements.organizer', ['name' => $organizer]) }}. {{ __('pages/evenements.charter') }}</p>
                        <div class="alert alert-info d-flex gap-2 small">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                            <p class="mb-0">{{ __('pages/evenements.address_hidden') }}</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($event->isUpcoming() && auth()->guest())
                                <a class="btn btn-primary" href="{{ $loginUrl }}"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>{{ __('pages/evenements.participate') }}</a>
                            @endif
                            <a class="btn btn-light" href="{{ route('evenements.index') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('pages/evenements.back') }}</a>
                            <button type="button" class="btn btn-primary-soft" hidden data-xm-share
                                    data-share-title="{{ $event->title }}" data-share-url="{{ route('evenements.show', $event->ulid) }}"
                                    data-share-copied="{{ __('pages/evenements.share_copied') }}" data-share-failed="{{ __('pages/evenements.share_failed') }}"><i class="bi bi-share me-1" aria-hidden="true"></i>{{ __('pages/evenements.share') }}</button>
                        </div>
                    </div>
                </article>
            </div>
            <aside class="col-lg-4 vstack gap-4" aria-label="{{ __('pages/evenements.others') }}">
                @guest
                    @include('public.evenements.join')
                @endguest
                @if ($others->isNotEmpty())
                    <section class="card" aria-labelledby="evenement-autres-titre">
                        <div class="card-header"><h2 class="h6 mb-0" id="evenement-autres-titre">{{ __('pages/evenements.others') }}</h2></div>
                        <ul class="list-group list-group-flush">
                            @foreach ($others as $item)
                                <li class="list-group-item d-flex gap-3">
                                    <a class="flex-shrink-0 rounded overflow-hidden d-block xm-news-thumb" href="{{ route('evenements.show', $item->ulid) }}" tabindex="-1" aria-hidden="true">
                                        <x-img class="xm-img-cover" :src="\App\Support\MediaUrl::resolve($item->image_path, \App\Models\Event::DEFAULT_COVER)" alt="" width="64" height="64" />
                                    </a>
                                    <div class="small">
                                        <h3 class="h6 mb-0 lh-sm"><a href="{{ route('evenements.show', $item->ulid) }}">{{ $item->title }}</a></h3>
                                        <span class="text-secondary">{{ $fmt->dayMonth($item->starts_at) }} {{ __('common.separator') }} {{ \App\Http\Resources\Events\EventResource::locationLabel($item) }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.public>
