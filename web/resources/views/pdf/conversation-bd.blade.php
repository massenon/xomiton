{{--
    Export PDF A4 d'une conversation en BD (P20-09, App\Application\Content\ConversationsBd\ConversationPdf) : rendu par
    dompdf, sans ressource distante. Polices embarquées : Liberation Sans (texte) et Montserrat Bold (titres, cris,
    cartouches). Chaque case : illustration (ou repli avec la description) + formes des bulles en SVG vectoriel + texte des
    bulles en vrai texte, posé aux coordonnées de BubbleGeometry (mm) : sélectionnable et lisible. Pied fixe : source et
    adresse sur chaque page. dompdf ne lit pas les variables CSS : couleurs écrites en valeur (marque : #0433C3, encre des
    bulles : #1B1630).
    Variables : $conversation, $panels (n, alt, image, shapes, texts, transcript), $count, $panelMm, $author, $questions,
    $url, $published, $date, $fonts.
--}}
@php($panelH = round($panelMm * 2 / 3, 2))
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $conversation->title }}</title>
    <style>
        @font-face { font-family: 'Liberation Sans'; font-style: normal; font-weight: normal; src: url('{{ $fonts['regular'] }}') format('truetype'); }
        @font-face { font-family: 'Liberation Sans'; font-style: normal; font-weight: bold; src: url('{{ $fonts['bold'] }}') format('truetype'); }
        @font-face { font-family: 'Liberation Sans'; font-style: italic; font-weight: normal; src: url('{{ $fonts['italic'] }}') format('truetype'); }
        @font-face { font-family: 'Montserrat XM'; font-style: normal; font-weight: bold; src: url('{{ $fonts['strong'] }}') format('truetype'); }
        @font-face { font-family: 'Montserrat XM'; font-style: normal; font-weight: normal; src: url('{{ $fonts['strong'] }}') format('truetype'); }
        @page { margin: 16mm 20mm 18mm 20mm; }
        body { font-family: 'Liberation Sans', sans-serif; font-size: 10pt; color: #1B1630; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #55556a; border-top: 0.4pt solid #b9bccd; padding-top: 1.5mm; text-align: center; }
        .kicker { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #6F2C91; margin: 0 0 1mm; }
        h1 { font-family: 'Montserrat XM', sans-serif; font-weight: bold; font-size: 18pt; color: #0433C3; margin: 0 0 2mm; }
        h2 { font-family: 'Montserrat XM', sans-serif; font-weight: bold; font-size: 11.5pt; color: #0433C3; margin: 5mm 0 2mm; page-break-after: avoid; }
        .lead { font-size: 10.5pt; margin: 0 0 1.5mm; }
        .meta { font-size: 8.5pt; color: #55556a; margin: 0 0 4mm; }
        .warning { border: 0.6pt solid #b7791f; background: #fff8e6; padding: 2mm 3mm; font-size: 9pt; margin: 0 0 4mm; }
        .panel { page-break-inside: avoid; margin: 0 0 5mm; }
        .counter { font-size: 8pt; color: #55556a; margin: 0 0 1mm; }
        .frame { position: relative; width: {{ $panelMm }}mm; height: {{ $panelH }}mm; border: 0.8pt solid #1B1630; overflow: hidden; background: #ffffff; }
        .art { position: absolute; left: 0; top: 0; width: {{ $panelMm }}mm; height: {{ $panelH }}mm; }
        .fallback { position: absolute; left: 0; top: 0; width: {{ $panelMm }}mm; height: {{ $panelH }}mm; background: #FBEFF5; }
        .fallback p { position: absolute; left: 12mm; right: 12mm; bottom: 10mm; font-size: 9pt; color: #55556a; font-style: italic; margin: 0; }
        .bubbles { position: absolute; left: 0; top: 0; width: {{ $panelMm }}mm; height: {{ $panelH }}mm; }
        .line { position: absolute; text-align: center; white-space: nowrap; color: #1B1630; }
        .transcript { font-size: 8.5pt; color: #33334a; margin: 1.5mm 0 0; padding-left: 5mm; }
        .transcript li { margin: 0 0 0.6mm; }
        .key { border-left: 1.6mm solid #0433C3; background: #e8edfb; padding: 2.5mm 4mm; font-weight: bold; margin: 0 0 3mm; page-break-inside: avoid; }
        ol.questions li { margin: 0 0 1.5mm; }
        .source { font-size: 8.5pt; color: #55556a; margin-top: 6mm; }
    </style>
</head>
<body>
<footer>{{ __('pages/conversations_bd.pdf.footer', ['url' => $url, 'date' => $date]) }}</footer>

<p class="kicker">{{ __('pages/conversations_bd.pdf.kicker') }}</p>
<h1>{{ $conversation->title }}</h1>
@if (trim((string) $conversation->summary) !== '')
    <p class="lead">{{ $conversation->summary }}</p>
@endif
<p class="meta">
    @if ($author){{ __('pages/conversations_bd.author_line', ['name' => $author]) }} · @endif
    {{ trans_choice('pages/conversations_bd.panels_count', $count, ['count' => $count]) }}
    @if ($conversation->theme) · {{ __('pages/conversations_bd.theme', ['theme' => $conversation->theme]) }}@endif
    @if ($conversation->audience) · {{ __('pages/conversations_bd.audience', ['audience' => $conversation->audience]) }}@endif
</p>
@if (! $published)
    <p class="warning">{{ __('pages/conversations_bd.pdf.private') }}</p>
@endif
@if ($conversation->content_warning)
    <p class="warning">{{ $conversation->content_warning }}</p>
@endif

@foreach ($panels as $panel)
    <div class="panel">
        <p class="counter">{{ __('conversations_bd.panel.counter', ['n' => $panel['n'], 'count' => $count]) }}</p>
        <div class="frame">
            @if ($panel['image'])
                <img class="art" src="{{ $panel['image'] }}" alt="{{ $panel['alt'] }}">
            @else
                <div class="fallback"><p>{{ __('conversations_bd.panel.image_pending') }} — {{ $panel['alt'] }}</p></div>
            @endif
            <img class="bubbles" src="{{ $panel['shapes'] }}" alt="">
            @foreach ($panel['texts'] as $text)
                <div class="line" style="left: {{ $text['left'] }}mm; top: {{ $text['top'] }}mm; width: {{ $text['width'] }}mm; height: {{ $text['line'] }}mm; line-height: {{ $text['line'] }}mm; font-size: {{ $text['size'] }}mm; font-family: '{{ $text['family'] }}'; font-weight: {{ $text['bold'] ? 'bold' : 'normal' }}; font-style: {{ $text['italic'] ? 'italic' : 'normal' }};">{!! $text['html'] !!}</div>
            @endforeach
        </div>
        @if ($panel['transcript'] !== [])
            <ol class="transcript">
                @foreach ($panel['transcript'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ol>
        @else
            <p class="transcript">{{ __('conversations_bd.panel.no_bubble') }}</p>
        @endif
    </div>
@endforeach

@if ($conversation->key_message)
    <h2>{{ __('pages/conversations_bd.key_message_title') }}</h2>
    <p class="key">{{ $conversation->key_message }}</p>
@endif

@if ($questions !== [])
    <h2>{{ __('pages/conversations_bd.questions_title') }}</h2>
    <ol class="questions">
        @foreach ($questions as $question)
            <li>{{ $question }}</li>
        @endforeach
    </ol>
@endif

<p class="source">{{ __('pages/conversations_bd.pdf.source', ['url' => $url]) }}</p>
</body>
</html>
