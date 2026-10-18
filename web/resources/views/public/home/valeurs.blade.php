{{-- Section 2 — Valeurs #valeurs (prototype index.php l.137-157) ; ancre visée par « Notre espace » et le pied de page. --}}
<section class="lp-section" id="valeurs" aria-labelledby="valeursTitre">
    <div class="container">
        <div class="text-center mx-auto mb-5 lp-intro-760">
            <h2 id="valeursTitre" class="lp-title">{{ __('pages/accueil.valeurs.title') }}</h2>
            <p class="text-secondary mb-0">{{ __('pages/accueil.valeurs.intro_1') }}<br>{{ __('pages/accueil.valeurs.intro_2') }}</p>
        </div>
        <div class="row g-4 g-lg-5">
            @foreach (__('pages/accueil.valeurs.items') as $item)
                <div class="col-md-4 text-center">
                    <x-img class="mb-4 lp-value-ico" :src="asset('assets/images/elements/'.$item['icon'])" :alt="$item['alt']" width="100" height="100" />
                    <h3 class="h4">{{ $item['title'] }}</h3>
                    <p class="mb-0">{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
