{{--
    En-tête des phases d'inscription : « Étape n sur 4 » + <x-stepper> (aria-current="step"), étapes 2 à 4 marquées facultatives (D4).
    Variables : $step (clé de phase), $steps (libellés).
--}}
@php
    $current = \App\Http\Controllers\Auth\Support\RegistrationSteps::number($step);
    $labels = collect($steps)->map(fn (string $label, int $i): string => $i === 0 ? $label : $label.' ('.__('auth.register.optional_step').')')->all();
@endphp
<p class="small text-body-secondary fw-bold text-uppercase mb-2">{{ __('auth.register.step_of', ['current' => $current, 'total' => count($steps)]) }}</p>
<x-stepper :steps="$labels" :current="$current" />
