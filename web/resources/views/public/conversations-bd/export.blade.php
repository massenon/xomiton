{{--
    « Télécharger » une conversation en BD (P20-09) : PDF A4 produit par le serveur (lien simple, fonctionne sans
    JavaScript) ; images PNG des cases et image « bande » produites dans le navigateur (public/bd-export.js chargé au clic ;
    boutons masqués sans JavaScript). État annoncé (role="status").
    Variables : $conversation (publiée), $root (sélecteur de la zone des cases), $pdfLabel (facultatif).
--}}
<section class="card no-print" aria-labelledby="bdc-export-titre" data-xm-bd-export-box>
    <div class="card-body vstack gap-2">
        <h2 class="h5 mb-0" id="bdc-export-titre">{{ __('pages/conversations_bd.export.title') }}</h2>
        <p class="mb-0 text-secondary">{{ __('pages/conversations_bd.export.text') }}</p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="{{ route('conversations-bd.pdf', ['slug' => $conversation->slug]) }}" rel="nofollow" download>
                <i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>{{ $pdfLabel ?? __('pages/conversations_bd.export.pdf') }}
            </a>
            @foreach (['panels' => 'images', 'strip' => 'image'] as $mode => $icon)
                <button type="button" class="btn btn-outline-primary" hidden data-xm-bd-export="{{ $mode }}" data-xm-bd-export-root="{{ $root }}"
                        data-xm-bd-title="{{ $conversation->title }}" data-xm-bd-source="{{ __('pages/conversations_bd.pdf.footer', ['url' => route('conversations-bd.show', ['slug' => $conversation->slug]), 'date' => \App\Support\LocaleFormatter::make()->longDate(now())]) }}"
                        data-xm-bd-working="{{ __('pages/conversations_bd.export.working') }}" data-xm-bd-done="{{ __('pages/conversations_bd.export.done') }}" data-xm-bd-failed="{{ __('pages/conversations_bd.export.failed') }}">
                    <i class="bi bi-{{ $icon }} me-1" aria-hidden="true"></i>{{ __($mode === 'strip' ? 'pages/conversations_bd.export.png_strip' : 'pages/conversations_bd.export.png_panels') }}
                </button>
            @endforeach
        </div>
        <p class="small text-secondary mb-0" role="status" aria-live="polite" data-xm-bd-export-status></p>
    </div>
</section>
