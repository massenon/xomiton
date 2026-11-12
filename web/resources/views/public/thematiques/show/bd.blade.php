{{-- P19 : BD publique liée à la fiche (lien BD ↔ fiche) : couverture (alt court), titre, lien vers /bd/{key}. Variable : $comic. --}}
<section class="card overflow-hidden" id="fiche-bd" aria-labelledby="fiche-bd-titre">
    <x-img class="img-fluid w-100" :src="\App\Support\MediaUrl::resolve($comic->image_path, 'assets/images/xomiton/covers/og-default.jpg')" :alt="__('pages/bd.cover_alt', ['title' => $comic->title])" width="1200" height="800" />
    <div class="card-body vstack gap-2">
        <h2 class="h5 card-title mb-0" id="fiche-bd-titre">{{ __('pages/violences_numeriques.fiche_comic_title') }}</h2>
        <p class="small text-secondary mb-0">{{ __('pages/violences_numeriques.fiche_comic_text') }}</p>
        <a class="btn btn-primary w-100" href="{{ route('bd.show', ['key' => $comic->key]) }}"><i class="bi bi-book me-1" aria-hidden="true"></i>{{ __('pages/violences_numeriques.fiche_comic_link', ['title' => $comic->title]) }}</a>
    </div>
</section>
