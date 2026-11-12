{{--
    Case d'une conversation en BD (P20-04, GRAMMAIRE_DES_BULLES.md) : figure 3:2 = illustration SANS texte (ou repli rose
    pâle avec la description de la scène tant que l'image manque) + bulles SVG au texte réel (BubbleSvg, positions en %,
    mises à l'échelle de la case de 320 à 1600 px) + figcaption : compteur et transcription dans l'ordre de lecture
    (BubbleTranscript : « Fifamè pense : … »), lue par les lecteurs d'écran, visible sur petit écran. Le SVG est caché
    des lecteurs d'écran (la transcription le remplace). Jumeau Vue : resources/js/components/bd/XmBdPanel.vue (parité).
    Props : bubbles (bulles au format de la grammaire, ordre de lecture), names (clé => nom), image (URL ou null), alt
    (description de la scène), n, count, title (titre de la conversation), priority (première case : image LCP), id.
--}}
@props(['bubbles' => [], 'names' => [], 'image' => null, 'alt' => '', 'n' => 1, 'count' => 1, 'title' => '', 'priority' => false, 'id' => null])
@php
    $bubbles = array_values(array_filter((array) $bubbles, 'is_array'));
    $lines = \App\Application\Content\ConversationsBd\BubbleTranscript::lines($bubbles, (array) $names);
    $alt = trim((string) $alt);
@endphp
<figure {{ $attributes->class(['xm-bdc-panel']) }} @if ($id) id="{{ $id }}" @endif data-xm-bdc-panel>
    <div class="xm-bdc-frame">
        <div class="xm-bdc-art">
            @if (is_string($image) && $image !== '')
                <img class="xm-bdc-img" src="{{ $image }}" alt="{{ $alt }}" title="{{ __('conversations_bd.panel.title', ['n' => $n, 'count' => $count, 'title' => $title]) }}" width="1200" height="800" loading="{{ $priority ? 'eager' : 'lazy' }}" decoding="async" @if ($priority) fetchpriority="high" @endif>
            @else
                <div class="xm-bdc-fallback">
                    <p class="xm-bdc-fallback-label">{{ __('conversations_bd.panel.image_pending') }}</p>
                    <p class="xm-bdc-fallback-text">{{ $alt }}</p>
                </div>
            @endif
        </div>
        <div class="xm-bdc-overlay">{!! \App\Application\Content\ConversationsBd\BubbleSvg::render($bubbles) !!}</div>
    </div>
    <figcaption class="xm-bdc-caption">
        <span class="xm-bdc-counter">{{ __('conversations_bd.panel.counter', ['n' => $n, 'count' => $count]) }}</span>
        @if ($lines === [])
            <span class="xm-bdc-transcript">{{ __('conversations_bd.panel.no_bubble') }}</span>
        @else
            <span class="visually-hidden">{{ __('conversations_bd.panel.transcript') }}</span>
            <ol class="xm-bdc-transcript">
                @foreach ($lines as $line)
                    <li @if ($line['thought']) class="xm-bdc-thought-line" @endif>{{ $line['text'] }}</li>
                @endforeach
            </ol>
        @endif
    </figcaption>
</figure>
