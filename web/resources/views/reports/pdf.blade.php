{{--
    Rapport PDF (P10-07, ADR-23) : rendu par dompdf, sans ressource distante, police DejaVu Sans. dompdf ne lit pas les
    variables CSS : les couleurs de la marque sont écrites en valeur (même teinte que --xm-primary).
--}}
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset->title }}</title>
    <style>
        @page { margin: 22mm 14mm 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1b1b24; }
        header.band { position: fixed; top: -22mm; left: -14mm; right: -14mm; height: 16mm; background: #0433c3; color: #fff; padding: 4mm 14mm 0 14mm; }
        header.band .site { font-size: 8pt; opacity: .85; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #6b6b7a; text-align: center; }
        h1 { font-size: 16pt; margin: 0 0 2mm; color: #0433c3; }
        h2 { font-size: 11pt; margin: 6mm 0 2mm; color: #0433c3; page-break-after: avoid; }
        .meta { color: #55556a; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
        th, td { border: 0.4pt solid #b9bccd; padding: 1.6mm 2mm; text-align: left; vertical-align: top; }
        th { background: #e8edfb; font-weight: bold; }
        td.num { text-align: right; white-space: nowrap; }
        td.masked { color: #6b6b7a; font-style: italic; text-align: right; }
        .note { font-size: 7.5pt; color: #55556a; margin: 0 0 3mm; }
        .detail td, .detail th { font-size: 7pt; padding: 1mm 1.4mm; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
<header class="band"><div class="site">{{ $siteName }}</div></header>
<footer>{{ $siteName }} — {{ __('steering.reports.confidential') }}</footer>

<h1>{{ $dataset->title }}</h1>
<p class="meta">{{ __('steering.reports.period') }} : {{ $dataset->periodLabel }} — {{ __('steering.reports.generated_at') }} : {{ $dataset->generatedAt }}</p>
@if ($dataset->hasMasked)
    <p class="note">{{ __('steering.reports.masked_note', ['min' => \App\Support\SmallCellMask::MIN]) }}</p>
@endif

@foreach ($dataset->tables as $table)
    <h2>{{ $table['title'] }}</h2>
    <table>
        <thead><tr>@foreach ($table['head'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($table['rows'] as $row)
                <tr>
                    @foreach ($row as $index => $cell)
                        @if ($cell === null)
                            <td class="masked">{{ __('steering.reports.masked') }}</td>
                        @else
                            <td @class(['num' => $index > 0])>{{ $cell }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach

@if ($detailHead !== null)
    <h2>{{ __('steering.reports.detail.sheet') }}</h2>
    <table class="detail">
        <thead><tr>@foreach ($detailHead as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($detailRows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
