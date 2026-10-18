{{--
    Article de la fiche (mise en page de thematique.php l.47-80) : couverture (LCP, alt descriptif), badges catégorie
    (listée seulement), type et « Disponible hors connexion » (bibliothèque active seulement, P5-10), h1, méta (type ·
    poids ou durée · date · téléchargements), résumé, puis une seule action principale (UX_GUIDELINES.md) : télécharger
    (adresse stable, jamais de lien signé dans la page), ouvrir le lien externe (`http(s)` seulement, nouvelle fenêtre
    annoncée, jamais d'iframe : la CSP n'a pas de frame-src) ou lire la vidéo / l'audio (lecteur natif sur
    `ressources.stream` : fichier direct avec plages Range, `preload="none"` : la page ne déclenche aucun téléchargement).
    Corps HTML nettoyé à l'écriture (PurifiedHtml) ; pour un média, il sert de transcription (plan §7 : `<track>` quand
    P12-03 stockera des sous-titres).
--}}
@php
    $kindColor = in_array($resource->kind->badge(), ['secondary', 'warning'], true) ? 'neutral' : $resource->kind->badge();
    $categoryColor = match ((string) $category?->color) { '' => 'primary', 'secondary' => 'neutral', default => (string) $category?->color };
    $isMedia = $resource->isPlayable();
    $meta = array_filter([
        $resource->kind->label(),
        $fileSize ?? ($resource->duration_minutes ? trans_choice('pages/ressources.minutes', (int) $resource->duration_minutes) : null),
        $resource->published_at ? __('pages/ressources.published_on', ['date' => $fmt->longDate($resource->published_at)]) : null,
        $downloadUrl && $resource->downloads_count > 0 ? trans_choice('pages/ressources.downloads', (int) $resource->downloads_count, ['count' => $fmt->number((int) $resource->downloads_count)]) : null,
    ]);
@endphp
<article class="card overflow-hidden" id="ressource" aria-labelledby="ressource-titre">
    <div class="xm-ratio-16x9">
        <x-img class="xm-img-cover" :src="$resource->cover_url" :alt="__('pages/ressources.cover_alt', ['title' => $resource->title])" width="1200" height="675" priority />
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-1 mb-2">
            @if ($category)
                <x-badge :color="$categoryColor" soft :icon="$category->icon ? \Illuminate\Support\Str::after($category->icon, 'bi-') : null">{{ $category->label }}</x-badge>
            @endif
            <x-badge :color="$kindColor" soft :icon="\Illuminate\Support\Str::after($resource->kind->icon(), 'bi-')">{{ $resource->kind->label() }}</x-badge>
            @if ($offlineEnabled && $resource->is_offline_available)
                <x-badge color="success" soft icon="wifi-off">{{ __('microcopy.offline.available_offline') }}</x-badge>
            @endif
        </div>
        <h1 class="h4" id="ressource-titre">{{ $resource->title }}</h1>
        <p class="small text-secondary">{{ implode(' '.__('common.separator').' ', $meta) }}</p>
        @if ($resource->summary)
            <p class="lead fs-6">{{ $resource->summary }}</p>
        @endif

        @if ($downloadUrl)
            <p class="mb-0">
                <a class="btn btn-primary" href="{{ $downloadUrl }}" rel="nofollow" download><i class="bi bi-download me-1" aria-hidden="true"></i>{{ $fileSize
                    ? __('pages/ressources.download_cta', ['kind' => $resource->kind->label(), 'size' => $fileSize])
                    : __('pages/ressources.download_cta_no_size', ['kind' => $resource->kind->label()]) }}</a>
            </p>
        @elseif ($resource->isExternal())
            <p class="mb-0 d-flex flex-wrap align-items-center gap-2">
                <a class="btn btn-primary" href="{{ $resource->external_url }}" target="_blank" rel="noopener noreferrer nofollow"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('pages/ressources.open_external') }}<span class="visually-hidden"> {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                <small class="text-secondary">{{ parse_url((string) $resource->external_url, PHP_URL_HOST) }}</small>
            </p>
        @endif

        @if ($isMedia)
            <h2 class="h5 mt-4">{{ __('pages/ressources.media_title') }}</h2>
            @if ($resource->kind === \App\Enums\ResourceKind::Video)
                <video class="w-100 rounded" controls preload="none" src="{{ $streamUrl }}" poster="{{ $resource->cover_url }}">{{ __('pages/ressources.media_unsupported') }}</video>
            @else
                <audio class="w-100" controls preload="none" src="{{ $streamUrl }}">{{ __('pages/ressources.media_unsupported') }}</audio>
            @endif
        @endif

        @if ($resource->body)
            @if (in_array($resource->kind, [\App\Enums\ResourceKind::Video, \App\Enums\ResourceKind::Audio], true))
                <h2 class="h5 mt-4">{{ __('pages/ressources.transcript_title') }}</h2>
            @endif
            <div @class(['xm-prose', 'mt-4' => ! $isMedia])>{!! $resource->body !!}</div>
        @endif

        @if ($showLegalNotice)
            <p class="small text-secondary mt-3 mb-0">{{ __('pages/ressources.legal_notice') }}</p>
        @endif
    </div>
</article>
