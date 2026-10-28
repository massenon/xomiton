{{--
    Mise en page UNIQUE des e-mails Xomiton (clients de messagerie : tableaux, styles en ligne, 600 px).
    Valeurs visuelles : App\Application\Mail\EmailTheme (jetons --xm-* recopiés en hexadécimal, piles de polices de repli).
    Mode sombre : prefers-color-scheme + [data-ogsc] (Outlook.com), repli = styles clairs en ligne. Pas de SVG, pas de
    variable CSS. $bodyHtml est déjà nettoyé, ses variables échappées et habillé par EmailStyler.
--}}
@php
    $t = \App\Application\Mail\EmailTheme::LIGHT;
    $font = \App\Application\Mail\EmailTheme::FONT_BODY;
    $heading = \App\Application\Mail\EmailTheme::FONT_HEADING;
    $width = \App\Application\Mail\EmailTheme::WIDTH;
@endphp
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $siteName }}</title>
    <style>
        body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
        img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
        a{overflow-wrap:anywhere}
        @media only screen and (max-width:620px){
            .xm-wrap{width:100% !important}
            .xm-pad{padding-left:20px !important;padding-right:20px !important}
            .xm-btn-tbl{width:100% !important}
            .xm-btn{display:block !important;text-align:center !important}
            .xm-code{font-size:28px !important;letter-spacing:6px !important}
        }
        @media (prefers-color-scheme:dark){
@foreach (\App\Application\Mail\EmailTheme::darkRules() as $selector => $rule)
            {{ $selector }}{ {!! $rule !!} }
@endforeach
        }
@foreach (\App\Application\Mail\EmailTheme::darkRules() as $selector => $rule)
        [data-ogsc] {{ $selector }}{ {!! $rule !!} }
@endforeach
    </style>
</head>
<body class="xm-bg" style="margin:0;padding:0;width:100%;background-color:{{ $t['page'] }};font-family:{!! $font !!};color:{{ $t['text'] }}">
@if ($preheader)
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">{{ $preheader }}{!! str_repeat('&#847;&zwnj;&nbsp;', 40) !!}</div>
@endif
    <table role="presentation" class="xm-bg" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $t['page'] }}">
        <tr>
            <td align="center" style="padding:24px 12px">
                <table role="presentation" class="xm-wrap xm-card xm-line" width="{{ $width }}" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:{{ $width }}px;background-color:{{ $t['card'] }};border:1px solid {{ $t['border'] }};border-radius:16px;overflow:hidden">
                    <tr>
                        <td class="xm-hdr xm-pad" bgcolor="{{ $t['header'] }}" style="background-color:{{ $t['header'] }};padding:24px 32px">
                            <img src="{{ \App\Application\Mail\EmailTheme::logoUrl() }}" width="{{ \App\Application\Mail\EmailTheme::LOGO_WIDTH }}" height="{{ \App\Application\Mail\EmailTheme::LOGO_HEIGHT }}" alt="{{ $siteName }}" style="display:block;width:{{ \App\Application\Mail\EmailTheme::LOGO_WIDTH }}px;height:auto;border:0;font-family:{!! $heading !!};font-size:20px;font-weight:800;color:#FFFFFF">
                        </td>
                    </tr>
                    <tr>
                        <td class="xm-pad" style="padding:32px 32px 16px">{!! $bodyHtml !!}</td>
                    </tr>
                    <tr>
                        <td class="xm-foot xm-pad xm-line" style="padding:20px 32px 24px;background-color:{{ $t['footer'] }};border-top:1px solid {{ $t['border'] }};font-family:{!! $font !!};font-size:13px;line-height:20px;color:{{ $t['muted'] }}">
                            <p class="xm-muted" style="margin:0 0 8px;color:{{ $t['muted'] }}"><strong>{{ __('mail.footer.tagline', ['site' => $siteName]) }}</strong></p>
                            <p class="xm-muted" style="margin:0 0 8px;color:{{ $t['muted'] }}">{{ __('mail.footer.help') }} <a href="mailto:{{ $supportEmail }}" class="xm-link" style="color:{{ $t['primary'] }};text-decoration:underline">{{ $supportEmail }}</a></p>
                            <p class="xm-muted" style="margin:0;color:{{ $t['muted'] }}">{{ __('mail.footer.privacy') }}</p>
@if ($unsubscribeUrl)
                            <p class="xm-muted" style="margin:12px 0 0;color:{{ $t['muted'] }}">{{ __('mail.footer.unsubscribe_reason') }} <a href="{{ $unsubscribeUrl }}" class="xm-link" style="color:{{ $t['primary'] }};text-decoration:underline">{{ __('mail.footer.unsubscribe') }}</a></p>
@endif
                        </td>
                    </tr>
                </table>
@if ($pixelUrl)
                <img src="{{ $pixelUrl }}" width="1" height="1" alt="" style="display:block;border:0">
@endif
            </td>
        </tr>
    </table>
</body>
</html>
