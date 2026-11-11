{{-- « À lire aussi » (prototype blog-details.php l.53-62) : autres actualités récentes, vignette décorative hors tabulation, titre lié, date. --}}
<section class="card" id="actualite-autres" aria-labelledby="actualite-autres-titre">
    <div class="card-header">
        <h2 class="h6 mb-0" id="actualite-autres-titre">{{ __('pages/actualites.others_title') }}</h2>
    </div>
    <ul class="list-group list-group-flush">
        @foreach ($others as $item)
            <li class="list-group-item d-flex gap-3">
                <a class="flex-shrink-0 rounded overflow-hidden d-block xm-news-thumb" href="{{ route('actualites.show', $item->slug) }}" tabindex="-1" aria-hidden="true">
                    <x-img class="xm-img-cover" :src="$item->cover_url" alt="" width="64" height="64" />
                </a>
                <div class="small">
                    <h3 class="h6 mb-0 lh-sm"><a href="{{ route('actualites.show', $item->slug) }}">{{ $item->title }}</a></h3>
                    @if ($item->published_at)
                        <time class="text-secondary" datetime="{{ $fmt->isoDate($item->published_at) }}">{{ $fmt->longDate($item->published_at) }}</time>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
