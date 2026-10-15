@props(['title', 'image' => null, 'icon' => 'inbox'])
<div {{ $attributes->class('text-center py-5 px-3') }}>
    @if ($image)<x-img :src="$image" alt="" width="160" class="mb-3" />@else<i class="bi bi-{{ $icon }} display-4 text-body-secondary" aria-hidden="true"></i>@endif
    <p class="h5 mt-2">{{ $title }}</p>
    @if (! $slot->isEmpty())<p class="xm-hint mx-auto" style="max-width:var(--xm-measure)">{{ $slot }}</p>@endif
    @isset($action)<div class="mt-3">{{ $action }}</div>@endisset
</div>
