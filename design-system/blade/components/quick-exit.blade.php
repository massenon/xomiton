@props(['url' => 'https://www.wikipedia.org'])
<button type="button" {{ $attributes->class('xm-quick-exit d-inline-flex align-items-center gap-2') }} onclick="window.xmQuickExit(@js($url))">
    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> {{ __('Quitter ce site') }}
    <span class="visually-hidden">{{ __('(ou appuie deux fois sur Échap)') }}</span>
</button>
