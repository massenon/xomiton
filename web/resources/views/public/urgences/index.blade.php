{{--
    Page /urgences (P4-06, UrgencesController ; prototype_social/urgences.php). Gabarit sobre 640 px pour tout le monde
    (Q18 a), tu (Q47). Ordre du prototype : bandeau SOS (seul rouge de la page, signaler-et-sos.md) → h1 → boutons
    d'appel (tel:) → fiches de l'annuaire (département facultatif, GET sans soumission au changement : WCAG 3.2.2) →
    lieux sûrs → plan de sécurité → autres actions. Tout est rendu côté serveur, sans appel réseau ni dépendance au
    paquet JavaScript : la page reste utilisable depuis le cache hors connexion (P5-10) ; seul le plan de sécurité
    s'appuie sur un script en ligne (nonce CSP) qui mémorise les cases sur l'appareil.
    Variables : $services (label, number, tel), $contacts, $truncated, $zone, $zones, $places (items, examples),
                $planItems, $seo.
--}}
@php
    $firstNumber = $services[0]['number'] ?? null;
@endphp
<x-layouts.plain :seo="$seo" body-class="xm-urgences-page">
    <div class="vstack gap-4">
        <div class="alert alert-danger d-flex flex-wrap align-items-center gap-3 mb-0 xm-urg-banner" id="urgences-sos">
            <i class="bi bi-exclamation-triangle-fill fs-3" aria-hidden="true"></i>
            <p class="flex-grow-1 mb-0"><strong>{{ __('pages/urgences.banner.title') }}</strong> {{ $firstNumber !== null
                ? __('pages/urgences.banner.text', ['number' => $firstNumber])
                : __('pages/urgences.banner.text_no_number') }}</p>
            <a class="btn btn-danger fw-bold" href="{{ route('sos') }}">{{ __('pages/urgences.banner.sos') }}<span class="visually-hidden"> {{ __('common.separator') }} {{ __('pages/urgences.banner.sos_hint') }}</span></a>
        </div>

        <header>
            <h1 class="h3 mb-1">{{ __('pages/urgences.title') }}</h1>
            <p class="text-secondary mb-0">{{ __('pages/urgences.intro') }}</p>
        </header>

        @include('public.urgences.services')
        @include('public.urgences.contacts')
        @include('public.urgences.places')
        @include('public.urgences.plan')

        <nav class="d-flex flex-wrap gap-2 no-print xm-urg-actions" aria-label="{{ __('pages/urgences.actions.label') }}">
            <a class="btn btn-primary" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('pages/urgences.actions.report') }}</a>
            @auth
                <a class="btn btn-primary-soft" href="{{ route('assistant') }}"><i class="bi bi-robot me-1" aria-hidden="true"></i>{{ __('pages/urgences.actions.assistant') }}</a>
            @else
                <button type="button" class="btn btn-primary-soft" data-bs-toggle="offcanvas" data-bs-target="#offcanvasChat" aria-controls="offcanvasChat"><i class="bi bi-robot me-1" aria-hidden="true"></i>{{ __('pages/urgences.actions.assistant') }}</button>
                <a class="btn btn-light" href="{{ route('connexion') }}">{{ __('pages/urgences.actions.sign_in') }}</a>
            @endauth
        </nav>
    </div>
    @guest
        <x-assistant-offcanvas :fab="false" />
    @endguest
</x-layouts.plain>
