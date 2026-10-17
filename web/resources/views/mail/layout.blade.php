{{--
    Mise en page des e-mails Xomiton (port de x_mail_layout() du prototype) : tableaux et styles en ligne pour les
    clients de messagerie. $bodyHtml est déjà nettoyé (HTMLPurifier à la saisie) et ses variables échappées.
    Les couleurs reprennent les jetons de la charte (bleu #0433C3, neutres chauds) : pas de CSS externe possible ici.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F3F8;font-family:Arial,Helvetica,sans-serif;color:#1B1830">
@if ($preheader)
    <div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $preheader }}</div>
@endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F3F8">
        <tr>
            <td align="center" style="padding:24px 12px">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:12px;overflow:hidden">
                    <tr>
                        <td style="background:#0433C3;padding:20px 28px">
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" height="32" style="display:block;height:32px;border:0">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:15px;line-height:1.6">{!! $bodyHtml !!}</td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#FAF9FC;border-top:1px solid #ECEAF2;font-size:12px;line-height:1.5;color:#6B6880">
                            {{ __('mail.footer.tagline', ['site' => $siteName]) }}<br>
                            {{ __('mail.footer.help') }} <a href="mailto:{{ $supportEmail }}" style="color:#0433C3">{{ $supportEmail }}</a>
@if ($unsubscribeUrl)
                            <p style="margin:12px 0 0"><a href="{{ $unsubscribeUrl }}" style="color:#6B6880">{{ __('mail.footer.unsubscribe') }}</a></p>
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
