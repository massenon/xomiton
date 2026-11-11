{{--
    Carte de ressource (liste /ressources ; prototype ressources.php l.63-74) : couverture 16:9 décorative hors tabulation
    (le titre porte le lien), badges doux de catégorie et de type, titre h3.h6 (la grille a un h2 visible), résumé, pied :
    poids du fichier, durée ou site du lien externe ; à droite, seulement si la bibliothèque hors connexion est active
    ($offlineEnabled, P5-10) : l'interrupteur « Hors ligne », ou le badge « Disponible hors connexion » si l'équipe l'a
    marquée (`is_offline_available` : précachée pour tout le monde, plan §7) — sans service worker, aucune promesse.
    Badge de catégorie seulement pour une catégorie listée (ListResources retire les autres).
    Pas <x-card> ni carte entièrement cliquable (interrupteur dans la carte) : fidélité au prototype, plan §7.
    Badge PDF `secondary` (rouge de marque) et lien `warning` doux (contraste) affichés en `neutral` (plan §7).
    Variables : $resource (catégorie et thématique chargées), $priority (bool : image LCP), $offlineEnabled, $fmt.
--}}
@php
    $slug = (string) $resource->slug;
    $pageUrl = route('ressources.show', $slug);
    $fileUrl = $resource->isDownloadable() ? route('ressources.download', $slug) : null;
    $kindColor = in_array($resource->kind->badge(), ['secondary', 'warning'], true) ? 'neutral' : $resource->kind->badge();
    $cardCategory = $resource->category;
    $categoryColor = match ((string) $cardCategory?->color) { '' => 'primary', 'secondary' => 'neutral', default => (string) $cardCategory?->color };
@endphp
<li class="col-sm-6 col-xl-4">
    <article class="card h-100 overflow-hidden xm-card-hover">
        <a class="xm-ratio-16x9 d-block" href="{{ $pageUrl }}" tabindex="-1" aria-hidden="true">
            <x-img class="xm-img-cover" :src="$resource->cover_url" alt="" width="640" height="360" :priority="$priority ?? false" />
        </a>
        <div class="card-body d-flex flex-column">
            <div class="d-flex flex-wrap gap-1 mb-2">
                @if ($cardCategory)
                    <x-badge :color="$categoryColor" soft :icon="$cardCategory->icon ? \Illuminate\Support\Str::after($cardCategory->icon, 'bi-') : null">{{ $cardCategory->label }}</x-badge>
                @endif
                <x-badge :color="$kindColor" soft :icon="\Illuminate\Support\Str::after($resource->kind->icon(), 'bi-')">{{ $resource->kind->label() }}</x-badge>
            </div>
            <h3 class="h6"><a href="{{ $pageUrl }}">{{ $resource->title }}</a></h3>
            <p class="small text-secondary flex-grow-1">{{ $resource->summary }}</p>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <small class="text-secondary">
                    @if ($fileUrl && $resource->file_size)
                        <i class="bi bi-download me-1" aria-hidden="true"></i>{{ $fmt->fileSize((int) $resource->file_size) }}
                    @elseif ($resource->duration_minutes)
                        <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ trans_choice('pages/ressources.minutes', (int) $resource->duration_minutes) }}
                    @elseif ($resource->isExternal())
                        <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ parse_url((string) $resource->external_url, PHP_URL_HOST) }}
                    @endif
                </small>
                @if ($offlineEnabled)
                    @if ($resource->is_offline_available)
                        <x-badge color="success" soft icon="wifi-off">{{ __('microcopy.offline.available_offline') }}</x-badge>
                    @else
                        @include('public.ressources.offline-switch', ['title' => (string) $resource->title, 'fileUrl' => \App\Application\Content\OfflineLibrary::fileUrl($resource), 'small' => true])
                    @endif
                @endif
            </div>
        </div>
    </article>
</li>
