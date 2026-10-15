{{-- $steps = ['Identité', 'Contact', …] ; $current = 1..n --}}
@props(['steps', 'current' => 1])
<ol {{ $attributes->class('xm-stepper list-unstyled d-flex flex-wrap gap-3 mb-4') }} aria-label="{{ __('Étapes') }}">
    @foreach ($steps as $i => $label)
        @php($n = $i + 1)
        <li class="xm-stepper__item {{ $n < $current ? 'is-done' : ($n === $current ? 'is-current' : '') }}" @if ($n === $current) aria-current="step" @endif>
            <span class="xm-stepper__dot" aria-hidden="true">@if ($n < $current)<i class="bi bi-check-lg"></i>@else{{ $n }}@endif</span>
            <span>{{ $label }}<span class="visually-hidden"> — {{ $n < $current ? __('terminée') : ($n === $current ? __('en cours') : __('à venir')) }}</span></span>
        </li>
    @endforeach
</ol>
