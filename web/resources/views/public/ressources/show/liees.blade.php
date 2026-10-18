{{-- Ressources de la même catégorie (complétées par les autres), même markup que les thématiques liées : vignette décorative, titre lié, type · poids ou durée. --}}
<section class="card" id="ressource-liees" aria-labelledby="ressource-liees-titre">
    <div class="card-header pb-0 border-0">
        <h2 class="h5 card-title mb-0" id="ressource-liees-titre">{{ __('pages/ressources.related_title') }}</h2>
    </div>
    <div class="card-body">
        <ul class="list-unstyled mb-0">
            @foreach ($related as $item)
                <li @class(['d-flex', 'mb-3' => ! $loop->last])>
                    <a class="flex-shrink-0 rounded-2 overflow-hidden d-block xm-res-thumb" href="{{ route('ressources.show', $item->slug) }}" tabindex="-1" aria-hidden="true">
                        <div class="xm-ratio-16x9"><x-img class="xm-img-cover" :src="$item->cover_url" alt="" width="88" height="50" /></div>
                    </a>
                    <div class="ms-3">
                        <h3 class="h6 card-title mb-0 lh-sm"><a href="{{ route('ressources.show', $item->slug) }}">{{ $item->title }}</a></h3>
                        <p class="small mb-0 text-secondary">{{ implode(' '.__('common.separator').' ', array_filter([
                            $item->kind->label(),
                            $item->isDownloadable() && $item->file_size ? $fmt->fileSize((int) $item->file_size) : null,
                            ! $item->isDownloadable() && $item->duration_minutes ? trans_choice('pages/ressources.minutes', (int) $item->duration_minutes) : null,
                        ])) }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
