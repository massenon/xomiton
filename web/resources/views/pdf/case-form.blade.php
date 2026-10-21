{{--
    Fiche de référence VBG / VDH en PDF A4 (P11-02, ADR-23) : rendu par dompdf, sans ressource distante, police DejaVu Sans.
    dompdf ne lit pas les variables CSS : les couleurs de la marque sont écrites en valeur (même teinte que --xm-primary).
    Pied « Confidentiel » fixe, répété sur chaque page ; cinq sections du Module 4 du manuel.
--}}
@php
    $urgencyLabel = static fn (?string $value): string => $value === null ? '—' : (string) __('admin_data.forms.urgencies.'.$value);
    $modeLabel = static fn (string $value): string => (string) __('admin_data.forms.modes.'.$value);
    $date = static fn (?string $value) => $value === null ? '—' : $format->shortDate($value);
    $dash = static fn (?string $value): string => $value === null || $value === '' ? '—' : $value;
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['reference'] }}</title>
    <style>
        @page { margin: 24mm 16mm 20mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1b1b24; }
        header.band { position: fixed; top: -24mm; left: -16mm; right: -16mm; height: 18mm; background: #0433c3; color: #fff; padding: 5mm 16mm 0 16mm; }
        header.band .site { font-size: 11pt; font-weight: bold; }
        header.band .sub { font-size: 7.5pt; opacity: .85; }
        footer { position: fixed; bottom: -13mm; left: 0; right: 0; font-size: 7.5pt; color: #6b6b7a; text-align: center; border-top: 0.4pt solid #b9bccd; padding-top: 2mm; }
        footer strong { color: #b3261e; }
        h1 { font-size: 15pt; margin: 0 0 1mm; color: #0433c3; }
        h2 { font-size: 10.5pt; margin: 6mm 0 2mm; color: #0433c3; page-break-after: avoid; }
        .meta { color: #55556a; margin: 0 0 3mm; font-size: 8.5pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
        th, td { border: 0.4pt solid #b9bccd; padding: 1.8mm 2.2mm; text-align: left; vertical-align: top; }
        th { background: #e8edfb; width: 34%; font-weight: bold; }
        .text { white-space: pre-line; }
        ul { margin: 0; padding-left: 5mm; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
<header class="band">
    <div class="site">{{ $siteName }}</div>
    <div class="sub">{{ __('admin_data.forms.pdf.subtitle') }}</div>
</header>
<footer><strong>{{ __('admin_data.forms.pdf.confidential') }}</strong> — {{ $sheet['reference'] }} — {{ __('admin_data.forms.pdf.footer') }}</footer>

<h1>{{ __('admin_data.forms.pdf.title', ['kind' => $sheet['kind']]) }}</h1>
<p class="meta">{{ $sheet['reference'] }} · {{ __('admin_data.forms.pdf.case', ['code' => $sheet['case_code']]) }} · {{ __('admin_data.forms.pdf.established', ['date' => $sheet['created_at'] ? $format->shortDate($sheet['created_at']) : '—', 'author' => $sheet['author'] ?? '—']) }}</p>

<h2>{{ __('admin_data.forms.sections.identification') }}</h2>
<table>
    <tr><th>{{ __('admin_data.forms.fields.pseudo') }}</th><td>{{ $dash($sheet['identification']['pseudo']) }}</td></tr>
    <tr><th>{{ __('admin_data.forms.pdf.sex_age') }}</th><td>{{ $dash($sheet['identification']['sex']) }} · {{ $dash($sheet['identification']['age']) }}</td></tr>
    <tr><th>{{ __('admin_data.forms.pdf.population') }}</th><td>{{ $dash($sheet['identification']['population']) }}</td></tr>
    <tr><th>{{ __('admin_data.forms.pdf.location') }}</th><td>{{ $dash($sheet['identification']['zone']) }} / {{ $dash($sheet['identification']['commune']) }}</td></tr>
</table>

<h2>{{ __('admin_data.forms.sections.facts') }}</h2>
<table>
    <tr><th>{{ __('admin_data.forms.pdf.date_place') }}</th><td>{{ $date($sheet['facts']['date']) }} · {{ $dash($sheet['facts']['place']) }}</td></tr>
    <tr><th>{{ __('admin_data.forms.pdf.type') }}</th><td>{{ $dash($sheet['facts']['type']) }}</td></tr>
    <tr><th>{{ __('admin_data.forms.fields.facts_author') }}</th><td>{{ $dash($sheet['facts']['author']) }}</td></tr>
    <tr><th>{{ __('admin_data.forms.fields.facts_description') }}</th><td class="text">{{ $dash($sheet['facts']['description']) }}</td></tr>
</table>

<h2>{{ __('admin_data.forms.sections.services') }}</h2>
<table>
    <tr><th>{{ __('admin_data.forms.pdf.services') }}</th><td>@if ($sheet['services']['items'] === []){{ __('admin_data.forms.pdf.none') }}@else<ul>@foreach ($sheet['services']['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif</td></tr>
    <tr><th>{{ __('admin_data.forms.fields.services_note') }}</th><td class="text">{{ $dash($sheet['services']['note']) }}</td></tr>
</table>

<h2>{{ __('admin_data.forms.sections.referral') }}</h2>
<table>
    <tr><th>{{ __('admin_data.forms.pdf.to') }}</th><td>{{ $dash($sheet['referral']['label']) }}</td></tr>
    <tr><th>{{ __('admin_data.forms.pdf.date_urgency') }}</th><td>{{ $date($sheet['referral']['date']) }} · {{ $urgencyLabel($sheet['referral']['urgency']) }}</td></tr>
    <tr><th>{{ __('admin_data.forms.fields.referral_reason') }}</th><td>{{ $dash($sheet['referral']['reason']) }}</td></tr>
</table>

<h2>{{ __('admin_data.forms.sections.consent') }}</h2>
<table>
    <tr><th>{{ __('admin_data.forms.pdf.consent') }}</th><td>{{ $sheet['consent']['given'] ? __('admin_data.forms.pdf.consent_given', ['mode' => $modeLabel($sheet['consent']['mode'])]) : __('admin_data.forms.pdf.consent_missing') }}</td></tr>
    <tr><th>{{ __('admin_data.forms.pdf.sharing') }}</th><td>{{ $sheet['consent']['sharing'] ? __('admin_data.forms.pdf.allowed') : __('admin_data.forms.pdf.not_allowed') }}</td></tr>
    <tr><th>{{ __('admin_data.forms.fields.consent_signatory') }}</th><td>{{ $dash($sheet['consent']['signatory']) }}</td></tr>
</table>
</body>
</html>
