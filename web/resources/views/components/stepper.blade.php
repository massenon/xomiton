{{--
    Indicateur d'étapes (prototype sign-up.php : ol.xm-stepper.list-unstyled.d-flex.flex-wrap.gap-3 > li.xm-stepper__item[.is-done|.is-current] > span.xm-stepper__dot + libellé).
    Étape courante : aria-current="step" ; état annoncé (terminée / en cours / à venir) en texte caché. Sauvegarde à chaque étape côté formulaire.
    Props : steps (['Identité', 'Contact', …]), current (1..n), label (nom accessible de la liste).
--}}
@props(['steps', 'current' => 1, 'label' => null])
<ol {{ $attributes->class('xm-stepper list-unstyled d-flex flex-wrap gap-3 mb-4') }} aria-label="{{ $label ?? __('components.stepper.label') }}">
    @foreach ($steps as $i => $step)
        @php($n = $i + 1)
        <li @class(['xm-stepper__item', 'is-done' => $n < $current, 'is-current' => $n === $current]){!! $n === $current ? ' aria-current="step"' : '' !!}>
            <span class="xm-stepper__dot" aria-hidden="true">@if ($n < $current)<i class="bi bi-check-lg"></i>@else{{ $n }}@endif</span>
            <span>{{ $step }}<span class="visually-hidden"> — {{ $n < $current ? __('components.stepper.done') : ($n === $current ? __('components.stepper.current') : __('components.stepper.todo')) }}</span></span>
        </li>
    @endforeach
</ol>
