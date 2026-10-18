{{--
    En-tête des phases d'inscription : « Étape n sur 4 » (suivi de « facultative » pour les phases 2 à 4) + <x-stepper>
    (aria-current="step"), étapes 2 à 4 marquées facultatives (D4).
    Variables : $step (clé de phase), $steps (libellés).
--}}
@php
    $current = \App\Http\Controllers\Auth\Support\RegistrationSteps::number($step);
    $optional = \App\Http\Controllers\Auth\Support\RegistrationSteps::isOptional($step);
    $labels = collect($steps)->map(fn (string $label, int $i): string => $i === 0 ? $label : $label.' ('.__('auth.register.optional_step').')')->all();
@endphp
<p class="small text-body-secondary fw-bold text-uppercase mb-2" data-xm-step-counter>{{ __($optional ? 'auth.register.step_of_optional' : 'auth.register.step_of', ['current' => $current, 'total' => count($steps)]) }}</p>
<x-stepper :steps="$labels" :current="$current" />
