{{--
    Avatar du thème Social (prototype inc/render.php : .avatar[.avatar-{taille}] > img.avatar-img.rounded-circle[alt][title]).
    Sans image : initiales dans .avatar-img.xm-avatar (role="img" + aria-label).
    Props :
      name    : obligatoire (alt « Avatar de … », title, initiales)
      src     : URL de l'image (null → initiales)
      size    : '' (48 px) | xs (35) | sm (40) | lg (64) | xl (82) | xxl (82, 128 ≥ 768 px) | xxxl (128)
      rounded : circle (défaut) | 2 (coins arrondis, ex. avatar du menu profil)
--}}
@props(['name', 'src' => null, 'size' => '', 'rounded' => 'circle'])
@php
    $px = ['xs' => 35, 'sm' => 40, '' => 48, 'lg' => 64, 'xl' => 82, 'xxl' => 82, 'xxxl' => 128][$size] ?? 48;
    $shape = $rounded === 'circle' ? 'rounded-circle' : 'rounded-2';
    $alt = __('components.avatar.alt', ['name' => $name]);
    $initials = collect(preg_split('/\s+/u', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<div {{ $attributes->class(['avatar', "avatar-$size" => $size !== '']) }}>
    @if ($src)
        <img class="avatar-img {{ $shape }}" src="{{ $src }}" alt="{{ $alt }}" title="{{ $name }}" width="{{ $px }}" height="{{ $px }}" loading="lazy">
    @else
        <span class="avatar-img {{ $shape }} xm-avatar" role="img" aria-label="{{ $alt }}" title="{{ $name }}">{{ $initials }}</span>
    @endif
</div>
