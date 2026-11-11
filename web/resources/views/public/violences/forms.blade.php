{{--
    Formes de violences (prototype violences.php l.63-80) : une carte par type actif du référentiel violence_types
    (libellé, icône, description administrés), signes à repérer de lang/fr/pages/violences.php (liste omise si absents).
--}}
<section aria-labelledby="formes-titre">
    <h2 class="h5 mb-3" id="formes-titre">{{ trans_choice('pages/violences.forms.title', count($forms)) }}</h2>
    <ul class="row g-3 list-unstyled mb-0">
        @foreach ($forms as $form)
            <li class="col-sm-6 col-xl-4">
                <article class="card card-body h-100" aria-labelledby="forme-{{ $form['slug'] }}">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="icon-md bg-primary bg-opacity-10 text-primary rounded-circle flex-shrink-0" aria-hidden="true"><i class="bi {{ $form['icon'] }}"></i></span>
                        <h3 class="h6 mb-0" id="forme-{{ $form['slug'] }}">{{ $form['label'] }}</h3>
                    </div>
                    @if (filled($form['description']))
                        <p @class(['small text-secondary', 'mb-0' => $form['signs'] === []])>{{ $form['description'] }}</p>
                    @endif
                    @if ($form['signs'] !== [])
                        <p class="small fw-bold mb-1" id="forme-{{ $form['slug'] }}-signes">{{ __('pages/violences.forms.signs_title') }}</p>
                        <ul class="small mb-0 ps-3" aria-labelledby="forme-{{ $form['slug'] }}-signes">
                            @foreach ($form['signs'] as $sign)
                                <li>{{ $sign }}</li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            </li>
        @endforeach
    </ul>
</section>
