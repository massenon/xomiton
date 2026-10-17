@props([
    'variant' => 'content',  // content | resource | news | event | contact | stat
    'title',
    'href' => null,          // lien unique = carte entièrement cliquable
    'badge' => null,
    'image' => null,
    'imageAlt' => '',
    'meta' => null,
])
<article {{ $attributes->class(['xm-card', 'xm-card--interactive position-relative' => $href, "xm-card--$variant", 'd-flex flex-column gap-3 h-100']) }}>
    @if ($image)
        <x-img :src="$image" :alt="$imageAlt" :title="$imageAlt ?: $title" class="rounded-3 w-100" style="aspect-ratio:16/9;object-fit:cover" />
    @endif
    @if ($badge)<div><span class="badge text-bg-primary">{{ $badge }}</span></div>@endif
    <h3 class="h5 mb-0">
        @if ($href)<a href="{{ $href }}" class="stretched-link text-reset text-decoration-none">{{ $title }}</a>@else{{ $title }}@endif
    </h3>
    @if ($meta)<p class="xm-hint mb-0">{{ $meta }}</p>@endif
    @if (! $slot->isEmpty())<div>{{ $slot }}</div>@endif
    @isset($actions)<div class="mt-auto d-flex gap-2 position-relative">{{ $actions }}</div>@endisset
</article>
