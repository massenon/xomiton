{{--
    « Fiches à télécharger » (prototype ressources.php l.82-93) : documents PDF publiés les plus récents, affichés sans
    filtre actif (la grille filtrée par type les répéterait, plan §7). Icônes en bleu (le rouge du prototype était
    décoratif, D5) ; méta « Document PDF · 1,2 Mo · Catégorie » (poids au lieu du nombre de pages, absent en base) ;
    vrai téléchargement par l'adresse stable `ressources.download` (nom accessible complété du titre, du type et du poids).
    Variables : $files (catégorie chargée), $fmt.
--}}
<section class="card" id="fiches" aria-labelledby="fiches-titre">
    <div class="card-header">
        <h2 class="h5 mb-0" id="fiches-titre"><i class="bi bi-file-earmark-pdf me-2 text-primary" aria-hidden="true"></i>{{ __('pages/ressources.files_title') }}</h2>
    </div>
    <ul class="list-group list-group-flush mb-0">
        @foreach ($files as $file)
            @php($size = $fmt->fileSize((int) $file->file_size))
            <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                <i class="bi bi-file-earmark-pdf-fill text-primary fs-4" aria-hidden="true"></i>
                <div class="flex-grow-1">
                    <h3 class="h6 fw-semibold mb-0"><a class="text-reset" href="{{ route('ressources.show', $file->slug) }}">{{ $file->title }}</a></h3>
                    <small class="text-secondary">{{ $file->category
                        ? __('pages/ressources.file_meta', ['kind' => $file->kind->label(), 'size' => $size, 'category' => $file->category->label])
                        : __('pages/ressources.file_meta_no_category', ['kind' => $file->kind->label(), 'size' => $size]) }}</small>
                </div>
                <a class="btn btn-sm btn-primary-soft" href="{{ route('ressources.download', $file->slug) }}" rel="nofollow" download><i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('common.download') }}<span class="visually-hidden"> {{ __('pages/ressources.download_hint', ['title' => $file->title, 'kind' => $file->kind->label(), 'size' => $size]) }}</span></a>
            </li>
        @endforeach
    </ul>
</section>
