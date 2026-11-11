{{-- Lecture hors connexion (P4-03, ADR-26 ; rendue seulement si la bibliothèque est active, P5-10) : badge si l'équipe a marqué la ressource (précachée pour tout le monde), sinon interrupteur par appareil (même composant et même clé que la liste ; fichier = source du lecteur pour un média). --}}
<section class="card" id="hors-ligne" aria-labelledby="hors-ligne-titre">
    <div class="card-header pb-0 border-0">
        <h2 class="h5 card-title mb-0" id="hors-ligne-titre">{{ __('pages/ressources.offline_title') }}</h2>
    </div>
    <div class="card-body">
        @if ($resource->is_offline_available)
            <p class="mb-0"><x-badge color="success" soft icon="wifi-off">{{ __('microcopy.offline.available_offline') }}</x-badge></p>
        @else
            <p class="small text-secondary">{{ __('pages/ressources.offline_text') }}</p>
            @include('public.ressources.offline-switch', ['slug' => (string) $resource->slug, 'pageUrl' => route('ressources.show', $resource->slug), 'title' => (string) $resource->title, 'fileUrl' => $offlineFile, 'small' => false])
        @endif
    </div>
</section>
