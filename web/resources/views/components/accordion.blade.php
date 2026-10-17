{{-- $items = [['q' => '…', 'a' => '…'], …] ; un seul panneau ouvert à la fois. --}}
@props(['items', 'name' => 'acc'])
<div x-data="{ open: null }" {{ $attributes->class('xm-accordion') }}>
    @foreach ($items as $i => $item)
        <div class="border-bottom">
            <h3 class="h6 mb-0">
                <button type="button" class="btn w-100 text-start d-flex justify-content-between align-items-center py-3 fw-bold"
                        id="{{ $name }}-h{{ $i }}" aria-controls="{{ $name }}-p{{ $i }}"
                        x-bind:aria-expanded="open === {{ $i }}" @click="open = open === {{ $i }} ? null : {{ $i }}">
                    {{ $item['q'] }}
                    <i class="bi bi-chevron-down" aria-hidden="true" x-bind:class="open === {{ $i }} && 'bi-chevron-up'"></i>
                </button>
            </h3>
            <div id="{{ $name }}-p{{ $i }}" role="region" aria-labelledby="{{ $name }}-h{{ $i }}" x-show="open === {{ $i }}" x-cloak class="pb-3 xm-prose">{!! $item['a'] !!}</div>
        </div>
    @endforeach
</div>
