{{--
    En-tête des phases d'inscription, version compacte : « Étape n sur 4 » (compteur) + <x-stepper> (aria-current="step").
    Seule l'étape courante garde son libellé visible (les autres restent lisibles par les lecteurs d'écran) ; les
    libellés sont simples (« Profil », « Sécurité », « Consentements ») : la possibilité de passer une étape est portée
    par le bouton « Passer cette étape » des écrans 2 à 4, jamais par un mot dans l'intitulé.
    Variables : $step (clé de phase), $steps (libellés).
--}}
@php
    $current = \App\Http\Controllers\Auth\Support\RegistrationSteps::number($step);
@endphp
<div class="xm-auth-progress">
    <p class="xm-auth-progress__count small text-body-secondary fw-bold text-uppercase mb-0" data-xm-step-counter>{{ __('auth.register.step_of', ['current' => $current, 'total' => count($steps)]) }}</p>
    <x-stepper :steps="$steps" :current="$current" class="xm-stepper--compact" />
</div>
