{{--
    Auto-évaluation confidentielle (prototype violences.php l.136-158 et script l.167-185), composant Alpine xmSelfCheck
    (resources/js/public/violences.js, chargé à la demande). Rien n'est transmis ni enregistré :
      - aucun <form> : rien ne peut partir vers le serveur, même sans JavaScript ou si le module ne charge pas ;
      - boutons type="button", calcul dans le navigateur, aucun appel réseau ni stockage (test Vitest) ;
      - autocomplete="off" sur les choix (pas de restauration par le navigateur), réponses effacées en quittant la page ;
      - une question = un <fieldset> dont l'énoncé est la <legend> (ACCESSIBILITE.md §4) ; changer une réponse masque
        le résultat déjà affiché (il ne correspondrait plus aux réponses).
    Les trois résultats sont rendus ici (textes de lang/fr), cachés : le module n'affiche que celui du score (seuils du
    prototype, ViolencesController). Sans JavaScript : questions lisibles, mention du calcul local, boutons absents.
--}}
<section class="card" id="auto-evaluation" aria-labelledby="check-titre" x-data="xmSelfCheck(@js($check))" x-on:pagehide.window="clear()">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 class="h5 mb-0" id="check-titre"><i class="bi bi-clipboard2-check me-2 text-primary" aria-hidden="true"></i>{{ __('pages/violences.check.title') }}</h2>
        <x-badge color="success" soft icon="shield-lock">{{ __('pages/violences.check.private') }}</x-badge>
    </div>
    <div class="card-body">
        <p class="small text-secondary">{{ __('pages/violences.check.intro', ['count' => $check['total']]) }}</p>
        <p class="small text-body-secondary" data-check-nojs x-show="false"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ __('pages/violences.check.no_js') }}</p>

        <ol class="list-unstyled vstack gap-3 mb-3">
            @foreach ($questions as $index => $question)
                <li class="border-bottom pb-2" data-check-question>
                    <fieldset class="xm-check-q">
                        <legend class="xm-choice-legend" id="check-q{{ $index }}"><span class="text-secondary me-1" aria-hidden="true">{{ $loop->iteration }}.</span><span class="visually-hidden">{{ __('pages/violences.check.question_label', ['number' => $loop->iteration, 'total' => $check['total']]) }} {{ __('common.separator') }} </span>{{ $question }}</legend>
                        <div class="btn-group flex-shrink-0">
                            <input type="radio" class="btn-check" name="check-q{{ $index }}" id="check-q{{ $index }}-oui" value="1" autocomplete="off" x-model.number="answers[{{ $index }}]">
                            <label class="btn btn-sm btn-outline-primary" for="check-q{{ $index }}-oui">{{ __('pages/violences.check.yes') }}</label>
                            <input type="radio" class="btn-check" name="check-q{{ $index }}" id="check-q{{ $index }}-non" value="0" autocomplete="off" x-model.number="answers[{{ $index }}]">
                            <label class="btn btn-sm btn-outline-primary" for="check-q{{ $index }}-non">{{ __('pages/violences.check.no') }}</label>
                        </div>
                    </fieldset>
                </li>
            @endforeach
        </ol>

        <div class="d-flex flex-wrap gap-2" data-check-tools hidden x-bind:hidden="false">
            <button type="button" class="btn btn-primary" x-on:click="evaluate()">{{ __('pages/violences.check.submit') }}</button>
            <button type="button" class="btn btn-light" x-on:click="reset()">{{ __('pages/violences.check.reset') }}</button>
        </div>
        <p class="small fw-semibold mt-2 mb-0" aria-live="polite" data-check-notice x-text="notice"></p>

        @foreach (['low' => 'success', 'medium' => 'warning', 'high' => 'danger'] as $level => $tone)
            <div class="alert alert-{{ $tone }} mt-3 mb-0" data-check-result="{{ $level }}" tabindex="-1" aria-labelledby="check-result-{{ $level }}" role="region" hidden x-bind:hidden="result !== '{{ $level }}'">
                <h3 class="h6 mb-1" id="check-result-{{ $level }}">{{ __("pages/violences.check.results.$level.title") }}</h3>
                <p class="mb-2 small">{{ __("pages/violences.check.results.$level.text") }}</p>
                <nav class="d-flex flex-wrap gap-2" aria-label="{{ __('pages/violences.check.actions_label') }}">
                    <a class="btn btn-sm btn-danger" href="{{ route('sos') }}">{{ __('pages/violences.hero.sos') }}</a>
                    <a class="btn btn-sm btn-primary" href="{{ route('signaler') }}">{{ __('pages/violences.hero.report') }}</a>
                    @auth
                        <a class="btn btn-sm btn-light" href="{{ route('annuaire') }}">{{ __('pages/violences.check.directory') }}</a>
                        <a class="btn btn-sm btn-light" href="{{ route('assistant') }}">{{ __('pages/violences.check.assistant') }}</a>
                    @else
                        <a class="btn btn-sm btn-light" href="{{ route('urgences') }}#urgences-contacts">{{ __('pages/violences.check.emergency_contacts') }}</a>
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="offcanvas" data-bs-target="#offcanvasChat" aria-controls="offcanvasChat">{{ __('pages/violences.check.assistant') }}</button>
                    @endauth
                </nav>
                <p class="small mt-2 mb-0 opacity-75">{{ __('pages/violences.check.note') }}</p>
            </div>
        @endforeach
    </div>
</section>
