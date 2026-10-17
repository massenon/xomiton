@props(['name', 'src' => null, 'size' => 40])
@php($initials = collect(preg_split('/\s+/u', trim($name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''))
@if ($src)
    <img {{ $attributes->class('rounded-circle') }} src="{{ $src }}" alt="{{ $name }}" title="{{ $name }}" width="{{ $size }}" height="{{ $size }}" loading="lazy" style="object-fit:cover">
@else
    <span {{ $attributes->class('xm-avatar') }} role="img" aria-label="{{ $name }}" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ $size * .4 }}px">{{ $initials }}</span>
@endif
