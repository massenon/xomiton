{{--
    Bouton ou lien-bouton (prototype : .btn.btn-{variante}[.btn-sm] avec <i class="bi bi-… me-1"> devant le libellé ; boutons icônes .btn-sm.btn-light + title + aria-label).
    Props :
      variant : primary | secondary (rouge : attention uniquement) | danger (alias rouge) | outline | outline-secondary | ghost | light | primary-soft | danger-soft | icon (btn-light carré, label obligatoire)
      size    : sm (36 px) | md (44 px) | lg (52 px)
      href    : rend un <a> (aria-disabled + .disabled si désactivé) au lieu d'un <button>
      type    : button | submit | reset
      loading : spinner + « Envoi… », bouton désactivé, aria-busy="true"
      icon    : nom Bootstrap Icons sans « bi- »
      label   : nom accessible (aria-label + title) ; obligatoire pour variant="icon" (exception sinon)
    États : survol --xm-primary-hover, actif --xm-primary-active, désactivé 60 % (social-xomiton.css) ; focus anneau 3 px --xm-focus-ring.
--}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button', 'loading' => false, 'icon' => null, 'label' => null])
@php
    $map = [
        'primary' => 'btn-primary', 'secondary' => 'btn-secondary', 'danger' => 'btn-danger',
        'outline' => 'btn-outline-primary', 'outline-secondary' => 'btn-outline-secondary',
        'ghost' => 'btn-link text-decoration-none', 'light' => 'btn-light',
        'primary-soft' => 'btn-primary-soft', 'danger-soft' => 'btn-danger-soft', 'icon' => 'btn-light',
    ];
    $sizes = ['sm' => 'btn-sm', 'md' => null, 'lg' => 'btn-lg'];
    if ($variant === 'icon' && ! $label) {
        throw new \InvalidArgumentException('<x-button variant="icon"> exige un attribut label (nom accessible).');
    }
    $tag = $href ? 'a' : 'button';
    $disabled = $loading || $attributes->get('disabled', false) !== false;
@endphp
<{{ $tag }} {{ $attributes->except('disabled')->class(['btn', $map[$variant] ?? 'btn-primary', $sizes[$size] ?? null, 'xm-btn-icon' => $variant === 'icon', 'disabled' => $disabled && $href])->merge($href ? ['href' => $href] : ['type' => $type]) }}
    @if ($label) aria-label="{{ $label }}" title="{{ $label }}" @endif
    @if ($loading) aria-busy="true" @endif
    @if ($disabled) @if ($href) aria-disabled="true" tabindex="-1" @else disabled @endif @endif>
    @if ($loading)<span @class(['spinner-border spinner-border-sm', 'me-1' => $variant !== 'icon']) aria-hidden="true"></span>@elseif ($icon)<i @class(['bi', "bi-$icon", 'me-1' => $variant !== 'icon']) aria-hidden="true"></i>@endif
    @if ($variant !== 'icon'){{ $loading ? __('common.sending') : $slot }}@endif
</{{ $tag }}>
