{{--
    Plan de sécurité (prototype urgences.php l.104-122 et son script l.135-150) : cases cochées mémorisées sur l'appareil
    seulement (localStorage `xm_plan_v1`, même clé et mêmes indices 0 à 7 que le prototype : rien n'est envoyé). Script en ligne autonome
    (public/urgences/plan-script, nonce CSP) plutôt qu'un module du paquet : il fonctionne même si public.js n'est pas
    en cache hors connexion. Sans JavaScript : cases utilisables mais non mémorisées ; compteur, barre, Imprimer et
    Réinitialiser restent masqués (attribut hidden retiré par le script). Annonce polie du compteur (aria-live).
    Rappel d'appareil partagé : un plan coché peut trahir la situation de la personne.
--}}
<section class="card" id="plan" aria-labelledby="plan-titre"
         data-xm-safety-plan data-key="xm_plan_v1"
         data-progress="{{ __('pages/urgences.plan.progress') }}"
         data-live="{{ __('pages/urgences.plan.progress_live') }}"
         data-reset-done="{{ __('pages/urgences.plan.reset_done') }}">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h5 mb-0" id="plan-titre"><i class="bi bi-clipboard2-heart me-2 text-primary" aria-hidden="true"></i>{{ __('pages/urgences.plan.title') }}</h2>
        {{-- Enveloppe sans classe d'affichage : `.d-flex` (!important) l'emporterait sur [hidden]. --}}
        <div class="no-print" data-plan-tools hidden>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge bg-primary bg-opacity-10 text-primary" data-plan-count aria-hidden="true">{{ __('pages/urgences.plan.progress', ['done' => 0, 'total' => count($planItems)]) }}</span>
                <button class="btn btn-sm btn-primary-soft" type="button" data-plan-print><i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('pages/urgences.plan.print') }}</button>
                <button class="btn btn-sm btn-light" type="button" data-plan-reset>{{ __('pages/urgences.plan.reset') }}</button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <p class="small text-secondary">{{ __('pages/urgences.plan.intro') }}</p>
        <div class="progress mb-3 xm-urg-plan-progress" data-plan-progress hidden aria-hidden="true">
            <div class="progress-bar" data-plan-bar></div>
        </div>
        <ul class="list-unstyled vstack gap-2 mb-0">
            @foreach ($planItems as $key => $item)
                <li class="form-check">
                    <input class="form-check-input" type="checkbox" id="plan{{ $key }}" data-plan-item="{{ $key }}">
                    <label class="form-check-label" for="plan{{ $key }}">{{ $item }}</label>
                </li>
            @endforeach
        </ul>
        <p class="visually-hidden" aria-live="polite" aria-atomic="true" data-plan-live></p>
        <p class="small text-secondary mt-3 mb-1">{{ __('pages/urgences.plan.local_only') }}</p>
        <p class="small text-secondary mb-0 no-print">{{ __('pages/urgences.plan.shared_device') }}</p>
    </div>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@include('public.urgences.plan-script')</script>
</section>
