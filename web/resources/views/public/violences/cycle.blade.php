{{--
    Cycle de la violence (prototype violences.php l.82-99) : liste ordonnée de 4 étapes, flèches décoratives ≥ 992 px.
    Tons : warning, danger, info, primary — « Justification » passe de secondary (rouge de marque dans Xomiton, D5) à info
    pour garder un seul rouge dans le schéma (plan §7). Pastille numérotée en text-bg-* (contraste géré par Bootstrap).
--}}
@php($tones = ['warning', 'danger', 'info', 'primary', 'success'])
<section class="card card-body" aria-labelledby="cycle-titre">
    <h2 class="h5" id="cycle-titre">{{ __('pages/violences.cycle.title') }}</h2>
    <p class="small text-secondary">{{ __('pages/violences.cycle.intro') }}</p>
    <ol class="xm-cycle row g-3 list-unstyled mb-0">
        @foreach ($cycle as $step)
            @php($tone = in_array($step['tone'] ?? '', $tones, true) ? $step['tone'] : 'primary')
            <li class="col-6 col-lg-3 position-relative">
                <div class="xm-cycle__step h-100 border rounded-3 p-3 text-center position-relative bg-{{ $tone }} bg-opacity-10">
                    <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill text-bg-{{ $tone }}" aria-hidden="true">{{ $loop->iteration }}</span>
                    <i class="bi bi-{{ $step['icon'] }} fs-2 text-{{ $tone }}" aria-hidden="true"></i>
                    <h3 class="h6 fw-bold mt-1 mb-1"><span class="visually-hidden">{{ __('pages/violences.cycle.step_label', ['number' => $loop->iteration]) }} {{ __('common.separator') }} </span>{{ $step['title'] }}</h3>
                    <p class="small text-secondary mb-0">{{ $step['text'] }}</p>
                </div>
                @unless ($loop->last)
                    <span class="xm-cycle__arrow d-none d-lg-block" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
                @endunless
            </li>
        @endforeach
    </ol>
    <p class="text-center small mt-3 mb-0 text-secondary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>{{ __('pages/violences.cycle.loop') }}</p>
</section>
